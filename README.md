# Access
**Route access control for the PHP Fat-Free Framework**

This plugin for [Fat-Free Framework](http://github.com/bcosca/fatfree) helps you control access to your routes.

* [Requirements](#requirements)
* [Basic usage](#basic-usage)
* [Advanced usage](#advanced-usage)
    * [Authorization failure](#authorization-failure)
    * [HTTP methods access control](#http-methods-access-control)
    * [Simple permission check](#simple-permission-check)
* [Rules processing order](#rules-processing-order)
    * [Path precedence](#path-precedence)
    * [Subject precedence](#subject-precedence)
    * [Routes uniqueness](#routes-uniqueness)
* [Wildcards](#wildcards)
* [Ini configuration](#ini-configuration)
* [Practical configuration examples](#practical-configuration-examples)
    * [Secure an admin area](#secure–an-admin-area)
    * [Secure MVC-like routes](#secure-mvc-like-routes)
    * [Secure RMR-like routes](#secure-rmr-like-routes)
* [API](#api)
* [Potential improvements](#potential-improvements)

## Requirements

This plugin takes care about authorization, not authentication. So before using it, make sure you have a way to identify your app users.

## Installation

To install this plugin, just copy the `lib/access.php` file into your `lib/` or your `AUTOLOAD` folder.

## Basic usage

Instantiate the plugin and define a default access policy (*allow* or *deny*) to your routes:

```php
$access=Access::instance();
$access->policy('allow'); // allow access to all routes by default
```

Then define a set of rules to protect a specific route:

```php
$access->deny('/secured.htm'); // globally deny access to /secured.htm
$access->allow('/secured.htm','admin'); // grant "admin" access to /secured.htm
```

or a group of routes:

```php
$access->deny('/protected*'); // globally deny access to any URI starting by /protected
$access->allow('/protected*','admin'); // grant "admin" access to any URI starting by /protected
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
So if we keep our first example:
* `$somebody='client'` would get a 403 error (Forbidden)
* `$somebody=''` would get a 401 error (Unauthorized => user should authenticate first)

You can provide a callback to the `authorize()` method, which will be triggered when authorization fails:
```php
$access->authorize($somebody,function($route,$subject){
  echo "$subject is denied access to $route";// $route is a method followed by a path
})
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

**IMPORTANT:** providing no HTTP method is equivalent to providing *all* HTTP methods. E.g:
```php
// the following are equivalent:
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

Rules are sorted from the most specific to the least specific path before being applied.
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
* 2,3,1 for the suject "zag"
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

## Wildcards

Wildcards can be used at various places:

* instead of a route verb, meaning "any verb": `* /path`
  * equivalent to `/path`
  * and `GET|HEAD|POST|PUT|PATCH|DELETE|CONNECT /path`
* in a route path, meaning "any character": `GET /foo/*/baz`
* instead of a subject, meaning "any subject": `$f3->allow('/','*')`
  * equivalent to `$f3->allow('/','')`
  * and `$f3->allow('/')`

## Ini configuration

Configuration is possible from within an .ini file, using the `ACCESS` variable.

Rules should be prefixed by the keywords "allow" or "deny" (case-insensitive):

```ini
[ACCESS]
policy = deny ;deny all routes by default

[ACCESS.rules]
ALLOW /foo = *
ALLOW /bar* = Albert,Jim
DENY /bar/baz = Jim
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
allow POST|PUT|PATCH|DELETE = superuser
```

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
See [here](#authorization-failure) for details about what happends when authorization fails.

## Potential improvements

* Enable support for named routes
* Think about `HEAD`