# Access
**Route access control for the PHP Fat-Free Framework**

This plugin for [Fat-Free Framework](http://github.com/bcosca/fatfree) helps you control access to your routes.

* [Requirements](#requirements)
* [Installation](#installation)
* [Basic usage](#basic-usage)
* [Advanced usage](#advanced-usage)
    * [Authorization failure](#authorization-failure)
    * [HTTP methods access control](#http-methods-access-control)
    * [Simple permission check](#simple-permission-check)
* [Rules processing order](#rules-processing-order)
    * [Path precedence](#path-precedence)
    * [Subject precedence](#subject-precedence)
    * [Routes uniqueness](#routes-uniqueness)
    * [Path case insensitivity](#path-case-insensitivity)
* [Wildcards and tokens](#wildcards-and-tokens)
* [Named routes](#named-routes)
* [Ini configuration](#ini-configuration)
* [Practical configuration examples](#practical-configuration-examples)
    * [Secure an admin area](#secure-an-admin-area)
    * [Secure MVC-like routes](#secure-mvc-like-routes)
    * [Secure RMR-like routes](#secure-rmr-like-routes)
    * [Secure a members-only site](#secure-a-members-only-site)
* [Pitfall](#pitfall)
* [API](#api)
* [Potential improvements](#potential-improvements)

## Requirements

This plugin takes care about authorization, not authentication. So before using it, make sure you have a way to identify your app users.

## Installation

To install this plugin, just copy the `lib/access.php` file into your `lib/` or your `AUTOLOAD` folder.

## Basic usage

Instantiate the plugin and define a default access policy (*allow* or *deny*) for your routes:

```php
$access=Access::instance();
$access->policy('allow'); // allow access to all routes by default
```

Then define a set of rules to protect a specific route:

```php
$access->deny('/secured.htm'); // globally deny access to /secured.htm
$access->allow('/secured.htm','admin'); // allow "admin" to access /secured.htm
```

or a group of routes:

```php
// globally deny access to any URI prefixed by /protected
$access->deny('/protected*');
// allow "admin" to access any URI prefixed by /protected
$access->allow('/protected*','admin');
```

And call the `authorize()` method where it fits your app best (before or after `$f3->run()`):

```php
$access->authorize($somebody); // e.g: $somebody=$f3->get('SESSION.user')
```

### That's it!

We have restricted access to `/secured.htm` and to all the URIs starting by `/protected`.
Any user not identified as "admin" will get an [error](#deny-access).

Bear in mind that "admin" can be anything meaningful to your application: a user name, group, role, right, etc..

So instead of "admin", we could have granted access to "admin@domain.tld" or "admin-role" or "Can access admin area".

For this reason, from now on we will call "admin" a *subject*.

Multiple subjects can be addressed by a single rule:

```php
$access->allow('/foo','tic,tac,toe'); // csv string
$access->allow('/foo',array('tic','tac','toe')); // array
```

**NB:** subject names can contain any character but commas.

## Advanced usage

### Authorization failure

A denied access will result in a 403 error if the subject is identified or a 401 if it is not.
In our first example:
* `$somebody='client'` would get a 403 error (Forbidden)
* `$somebody=''` would get a 401 error (Unauthorized => user should authenticate first)

You can provide a callback to the `authorize()` method, which will be triggered when authorization fails:
```php
$access->authorize($somebody,function($route,$subject){
  echo "$subject is denied access to $route";// $route is a method followed by a path
});
```
The default behaviour (403/401) is then skipped, unless the fallback returns FALSE.

### HTTP methods access control

Route permissions can be defined at HTTP method level:

```php
$access->deny('/path');
$access->allow('GET /path');
$access->allow('POST|PATCH|PUT|DELETE /path','admin');
```
In this example, only "admin" can modify `/path`. Any other subject can only `GET` it.

**IMPORTANT:** providing no HTTP method is equivalent to providing *all* HTTP methods (unless you're using named routes, [see below](#named-routes)).

E.g:
```php
// the following rules are equivalent:
$access->deny('/path');
$access->deny('GET|HEAD|POST|PUT|PATCH|DELETE|CONNECT /path');
```

### Simple permission check

If you need to check access permissions to a route for a specific subject, use the `granted()` method:
```php
if ($access->granted('/admin/part1',$somebody)) {
  // Access granted
} else {
  // Access denied
}
```

This method performs a simple check and doesn't take any action (no error thrown).

## Rules processing order

### Path precedence

Rules are sorted from the most specific to the least specific path before being processed.
So the following rules:

```php
$access->deny('/admin*','mike');
$access->deny('/admin/blog/foo','mike');
$access->allow('/admin/blog','mike');
$access->allow('/admin/blog/foo/bar','mike');
$access->deny('/admin/blog/*/bar','mike');
```

are processed in the following order:

```php
$access->allow('/admin/blog/foo/bar','mike');
$access->deny('/admin/blog/*/bar','mike');
$access->deny('/admin/blog/foo','mike');
$access->allow('/admin/blog','mike');
$access->deny('/admin*','mike');
```

**IMPORTANT:** the first rule for which the path matches applies. If no rule matches, the default policy applies.

### Subject precedence

Specific subject rules are processed before global rules.
So the following rules:

```php
$access->allow('/part2');// rule #1
$access->deny('/part1/blog','zag');// rule #2
$access->allow('/part1','zig,zag');// rule #3
```

are processed in the following order:
* 2,3,1 for the subject "zag"
* 3,1 for the subject "zig"

### Routes uniqueness

Rules are indexed by subject name and routes, so you can't have two rules for the same subject and the same route.
If the case arises, the second rule erases the first:

```php
$access->allow('/part1','Dina');// rule #1
$access->deny('/part1','Dina');// rule #2
$access->allow('POST /part1','Dina,Misha');// rule #3
$access->deny('/part1','Dina');// rule #4
```
In this example:
* rule #1 is ignored
* rule #3 is ignored for Dina only (not for Misha)

### Path case insensitivity

For security purposes, paths are considered case insensitive, no matter the value of the framework `CASELESS` variable.

Therefore, the following rules are equivalent:

```php
$access->deny('/restricted/area');
$access->deny('/RESTRICTED/AREA');
$access->deny('/rEsTrIcTeD/aReA');
```

## Wildcards and tokens

Wildcards can be used at various places:

* instead of a route verb, meaning "any verb": `* /path`
  * equivalent to `/path`
  * equivalent to `GET|HEAD|POST|PUT|PATCH|DELETE|CONNECT /path`
* in a route path, meaning "any character": `GET /foo/*/baz`
* instead of a subject, meaning "any subject": `$f3->allow('/','*')`
  * equivalent to `$f3->allow('/','')`
  * equivalent to `$f3->allow('/')`

**NB**: wildcards match empty strings, so `/admin*` match `/admin`.

Routes tokens are also supported, so `$f3->allow('/blog/@id/@slug')` is recognized.

Since the plugin doesn't make use of the token names, you can as well drop them: `$f3->allow('/blog/@/@')`

In other words, `@` is a wildcard for any character which is not a forward slash,
whereas `*` matches everything, including forward slashes.

**IMPORTANT**: read the [Pitfall](#pitfall) section.

## Named routes

If you're using [named routes](https://github.com/bcosca/fatfree#named-routes),
you can directly refer to their aliases: `$f3->allow('@blog_entry')`;

In that case, providing no HTTP method is equivalent to providing the methods which are actually mapped to the given route. See:

```php
$f3->route('GET|POST @admin_user_edit: /admin/user/@id','Class->edit');
$f3->route('DELETE @admin_user_delete: /admin/user/@id','Class->delete');

// the following rules are equivalent:
$access->deny('@admin_user_edit');
$access->deny('GET|POST @admin_user_edit');
$access->deny('GET|POST /admin/user/@id');
```

## Ini configuration

Configuration is possible from within an .ini file, using the `ACCESS` variable.

Rules should be prefixed by the keywords "allow" or "deny" (case-insensitive):

```ini
[ACCESS]
policy = deny ;deny all routes by default

[ACCESS.rules]
ALLOW /foo = *
ALLOW /bar* = Albert,Jean-Louis
DENY /bar/baz = Jean-Louis
```

It works with HTTP verbs as well:
```ini
[ACCESS.rules]
allow GET|POST /foo = Jim
allow * /bar = Albert,Jim
deny PUT /bar = Jim
```

## Practical configuration examples

### Secure an admin area

```ini
[ACCESS.rules]
allow /admin = * ; login form
deny /admin/* = *
allow /admin/* = superuser
```

### Secure MVC-like routes

```ini
[ACCESS.rules]
deny /*/edit = *
deny /*/create = *
allow /*/edit = superuser
allow /*/create = superuser
```

### Secure RMR-like routes

```ini
[ACCESS.rules]
deny * /* = *
deny GET /* = *
allow POST|PUT|PATCH|DELETE /* = superuser
```

### Secure a members-only site

```ini
ACCESS.policy = deny

[ACCESS.rules]
allow / = * ; login form
allow /* = member
```

## Pitfall

### Static routes overriding dynamic routes

Be careful when having static routes overriding dynamic routes.

Although not advised, the following setup is made possible by the framework:

```php
$f3->route('GET /admin/user/@id','User->edit');
$f3->route('GET /admin/user/new','User->create');
```

From an authorization point of view, we may be tempted to write:

```php
$access->deny('/admin*','*');// deny access to all admin paths by default
$access->allow('/admin/user/@id','edit_role');// allow edit_role to access /admin/user/@id
$access->allow('/admin/user/new','create_role');// allow create_role to access /admin/user/new
```

Doing so, we might think that the `edit_role` can't access the `/admin/user/new` path, but this is an illusion.

Indeed, the `@id` token match any string, including `new`.

To be convinced of this, just think that there's no difference between `/admin/user/@id` and `/admin/user/@anything`.

So in order to achieve a complete separation of roles, the correct configuration would be, in this situation:

```php
$access->deny('/admin*','*');// deny access to all admin paths by default
$access->allow('/admin/user/@id','edit_role');// allow edit_role to access /admin/user/@id.
$access->deny('/admin/user/new','edit_role');// ... but not /admin/user/new
$access->allow('/admin/user/new','create_role');// allow create_role to access /admin/user/new
```

A clearer setup would be:

* either to define one single path `/admin/user/@id` with `id=new` being handled inside a single controller
* or to define two unambiguous paths, for example `/admin/user/@id` and `/admin/new-user`

## API

```php
$access = Access::instance();
```

### policy( $default=NULL )

**Get/set the default policy (default='allow')**

```php
$access->policy('deny');
echo $access->policy();// 'deny'
```

### allow( $route, $subjects='' )

**Allow specified subject(s) to access a given route**

```php
$access->allow('/path'); // Grant "all" access to /path
$access->allow('/path',''); // idem
$access->allow('/path','*'); // idem
$access->allow('POST /foo','tip-top'); // Allow "tip-top" to POST /foo
```

### deny( $route, $subjects='' )

**Deny specified subject(s) access to a given route**

```php
$access->deny('/path'); // Deny "all" access to /path
$access->deny('/path',''); // idem
$access->deny('/path','*'); // idem
$access->deny('POST /foo','tip-top'); // Deny "tip-top" access to POST /foo
```

### granted( $route, $subject='' )

**Return TRUE if the given subject is granted access to the given route**

```php
if ($access->granted('/admin/part1',$somebody)) {
  // Access granted
} else {
  // Access denied
}
```

NB: you can also check access against a set of subjects. This is useful for example if you've implemented
a system of user roles or groups:

```php
$access->granted('/admin/part1',array('customer')); // FALSE
$access->granted('/admin/part1',array('customer','admin')); // TRUE
```

### authorize( $subject='', $ondeny=NULL )

**Return TRUE if the given subject is granted access to the current route**

If `$subject` is not provided, authorization is performed against "any" subject.

`$ondeny` should be a valid F3 callback (either a PHP callable or a string)

```php
$access->authorize(); // authorize "any" subject
$access->authorize('admin',function($route,$subject){
  echo "$subject is denied access to $route";// 'admin is denied access to GET /path'
});
$access->authorize('admin','My\App->forbidden');
```
See [here](#authorization-failure) for details about what happens when authorization fails.

NB: you can also perform authorization against a set of subjects. This is useful for example if you've implemented
a system of user roles or groups: just pass the array of roles/groups to authorize a user. E.g:

```php
$access->authorize(array('customer')); // unauthorized
$access->authorize(array('customer','admin')); // authorized
```

## Potential improvements

* Think about `HEAD` and `CONNECT`: should they be authorized or consistently allowed?
