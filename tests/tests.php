<?php

class Tests {

    function run($f3) {
        $test=new \Test;
        //Default policy: allow
        $access=new \Access();
        $access->policy('allow');
        $access->deny('/back','*');
        $access->allow('/back','admin,prod');
        $access->deny('/back/users','prod');
        $test->expect(
            $access::ALLOW==$access->policy(),
            'Default policy: '.$access::ALLOW
        );
        $test->expect(
            $access->granted('GET /blog','client'),
            'Access granted by default'
        );
        $test->expect(
            !$access->granted('GET /back','client'),
            'Access to a specific path denied to all'
        );
        $test->expect(
            $access->granted('GET /back','prod'),
            'Access to a path granted to a specific subject'
        );
        $test->expect(
            !$access->granted('GET /back/users','prod'),
            'Access to a subpath denied to a specific subject'
        );
        //Default policy: deny
        $access=new \Access();
        $access->policy('deny');
        $access->allow('/admin','admin,prod');
        $access->deny('/admin/part2','*');
        $access->allow('/admin/part2','admin');
        $test->expect(
            $access::DENY==$access->policy(),
            'Default policy: '.$access::DENY
        );
        $test->expect(
            !$access->granted('GET /blog','client'),
            'Access denied by default'
        );
        $test->expect(
            !$access->granted('GET /admin','client') && $access->granted('GET /admin','admin') && $access->granted('GET /admin','prod'),
            'Access to a specific path granted to specific subjects'
        );
        $test->expect(
            $access->granted('GET /admin/part2','admin'),
            'Access to a subpath granted to a specific subject (subpath precedence)'
        );
        $test->expect(
            !$access->granted('GET /admin/part2','prod'),
            'Access to a subpath denied to others (subpath precedence)'
        );
        //Wildcards
        $access=new \Access();
        $access->policy('allow');
        $access->deny('/admin*');
        $access->allow('/admin*','admin');
        $test->expect(
            !$access->granted('/admin') && !$access->granted('/admin/foo/bar') &&
            $access->granted('/admin','admin') && $access->granted('/admin/foo/bar','admin'),
            'Wildcard suffix'
        );
        $access->deny('/*/edit');
        $access->allow('/*/edit','admin');
        $test->expect(
            !$access->granted('/blog/entry/edit') && $access->granted('/blog/entry/edit','admin'),
            'Wildcard prefix'
        );
        $access->allow('/admin');
        $access->allow('/admin/special/path');
        $test->expect(
            $access->granted('/admin') && !$access->granted('/admin/foo/bar') &&
            $access->granted('/admin','admin') && $access->granted('/admin/foo/bar','admin') &&
            $access->granted('/admin/special/path') && $access->granted('/admin/special/path','admin'),
            'Wildcard precedence order'
        );
        //Tokens
        $access=new \Access();
        $access->deny('/@lang/foo');
        $test->expect(
            !$access->granted('/en/foo') && $access->granted('/en/bar/foo'),
            'Route tokens support'
        );
        $access->deny('/foo/@/baz');
        $test->expect(
            !$access->granted('/foo/bar/baz') && $access->granted('/foo/bar/baz/bis'),
            'Route tokens optional naming'
        );
        //Named routes
        $f3->route('GET @blog_entry:/blog/@id/@slug','Blog->Entry');
        $access->deny('@blog_entry');
        $test->expect(
            !$access->granted('/blog/1/hello') && $access->granted('/blog/1/hello/form') && $access->granted('/blog/1'),
            'Named routes support'
        );
        //Verb-level control
        $access=new \Access();
        $access->policy('allow');
        $access->deny('POST|PUT|DELETE /blog/entry','*');
        $access->allow('* /blog/entry','admin');
        $test->expect(
            $access->granted('GET /blog/entry','client') && !$access->granted('PUT /blog/entry','client') &&
            $access->granted('PUT /blog/entry','admin'),
            'Verb-level access control'
        );
        //Multiple subjects
        $test->expect(
            $access->granted('GET /blog/entry',array('client','customer')) &&
            !$access->granted('PUT /blog/entry',array('client','customer')) &&
            $access->granted('PUT /blog/entry',array('client','admin')),
            'Check access for a set of subjects'
        );
        //Authorize method
        $f3->HALT=FALSE;
        $f3->VERB='GET';
        $f3->PATH='/blog/entry';
        $f3->clear('ERROR');
        $f3->ONERROR=function($f3){};//do nothing
        $test->expect(
            $access->authorize() && !$f3->get('ERROR.code'),
            'Authorize an unidentified subject'
        );
        $f3->VERB='POST';
        $f3->clear('ERROR');
        $f3->ONERROR=function($f3){};//do nothing
        $test->expect(
            !$access->authorize() && $f3->get('ERROR.code')==401,
            'Unauthorize an unidentified subject (401 error)'
        );
        $f3->clear('ERROR');
        $f3->ONERROR=function($f3){};//do nothing
        $test->expect(
            $access->authorize('admin') && !$f3->get('ERROR.code'),
            'Authorize an identified subject'
        );
        $f3->clear('ERROR');
        $f3->ONERROR=function($f3){};//do nothing
        $test->expect(
            !$access->authorize('client') && $f3->get('ERROR.code')==403,
            'Unauthorize an identified subject (403 error)'
        );
        $f3->clear('ERROR');
        $f3->ONERROR=function($f3){};//do nothing
        $test->expect(
            $access->authorize(array('client','admin')) && !$f3->get('ERROR.code'),
            'Authorize a set of identified subjects'
        );
        $f3->clear('ERROR');
        $f3->ONERROR=function($f3){};//do nothing
        $test->expect(
            !$access->authorize(array('client','customer')) && $f3->get('ERROR.code')==403,
            'Unauthorize a set of identified subjects'
        );
        //Config variable
        $f3->HALT=TRUE;
        $f3->ONERROR=NULL;
        $f3->set('ACCESS.policy','deny');
        $f3->set('ACCESS.rules',array(
            'ALLOW * /foo' => '*',
            'DENY DELETE /foo' => '*',
            'ALLOW DELETE /foo' => 'admin',
        ));
        $access=new \Access();
        $test->expect(
            !$access->granted('/') && !$access->granted('/','admin'),
            'ACCESS.default config variable'
        );
        $test->expect(
            $access->granted('GET /foo') && !$access->granted('DELETE /foo') && $access->granted('DELETE /foo','admin'),
            'ACCESS.rules config variable'
        );
        $f3->set('results',$test->results());
    }

    function afterRoute($f3) {
        $f3->set('active','Access');
        echo \Preview::instance()->render('tests.htm');
    }

}
