<?php

class Access extends \Prefab {

    //Constants
    const
        DENY='deny',
        ALLOW='allow';

    /** @var string Default policy */
    protected $policy=self::ALLOW;

    /** @var array */
    protected $rules=array();

    /**
     * Define an access rule to a route
     * @param bool $accept
     * @param string $route
     * @param string|array $subjects
     * @return self
     */
    function rule($accept,$route,$subjects='') {
        if (!is_array($subjects))
            $subjects=explode(',',$subjects);
        list($verbs,$path)=$this->parseRoute($route);
        foreach($subjects as $subject)
            foreach($verbs as $verb)
                $this->rules[$subject?:'*'][$verb][$path]=$accept;
        return $this;
    }

    /**
     * Give access to a route
     * @param string $route
     * @param string|array $subjects
     * @return self
     */
    function allow($route,$subjects='') {
        return $this->rule(TRUE,$route,$subjects);
    }

    /**
     * Deny access to a route
     * @param string $route
     * @param string|array $subjects
     * @return self
     */
    function deny($route,$subjects='') {
        return $this->rule(FALSE,$route,$subjects);
    }

    /**
     * Get/set the default policy
     * @param string $default
     * @return self|string
     */
    function policy($default=NULL) {
        if (!isset($default))
            return $this->policy;
        if (in_array($default=strtolower($default),array(self::ALLOW,self::DENY)))
            $this->policy=$default;
        return $this;
    }

    /**
     * Return TRUE if the given subject is granted access to the given route
     * @param string $route
     * @param string $subject
     * @return bool
     */
    function granted($route,$subject='') {
        list($verbs,$uri)=$this->parseRoute($route);
        $verb=$verbs[0];//we shouldn't get more than one verb here
        $specific=isset($this->rules[$subject][$verb])?$this->rules[$subject][$verb]:array();
        $global=isset($this->rules['*'][$verb])?$this->rules['*'][$verb]:array();
        $rules=$specific+$global;//subject-specific rules have precedence over global rules
        krsort($rules);//specific paths are processed first
        foreach($rules as $path=>$rule)
            if (preg_match('/^'.preg_replace('/@\w*/','[^\/]+',str_replace('\*','.*',preg_quote($path,'/'))).'$/',$uri))
                return $rule;
        return $this->policy==self::ALLOW;
    }

    /**
     * Authorize a given subject
     * @param string $subject
     * @param callable|string $ondeny
     * @return bool
     */
    function authorize($subject='',$ondeny=NULL) {
        $f3=\Base::instance();
        if (!$this->granted($route=$f3->VERB.' '.$f3->PATH,$subject) &&
            (!isset($ondeny) || FALSE===$f3->call($ondeny,array($route,$subject)))) {
            $f3->error($subject?403:401);
            return FALSE;
        }
        return TRUE;
    }

    /**
     * Parse a route string
     * Possible route formats are:
     * - GET /foo
     * - GET|PUT /foo
     * - /foo
     * - * /foo
     * @param $str
     * @return array
     */
    protected function parseRoute($str) {
        $verbs=$path='';
        if (preg_match('/^\h*(\*|[\|\w]*)\h*(\H+)/',$str,$m)) {
            list(,$verbs,$path)=$m;
            if ($path[0]=='@')
                $path=\Base::instance()->ALIASES[substr($path,1)];
        }
        if (!$verbs || $verbs=='*')
            $verbs=\Base::VERBS;
        return array(explode('|',$verbs),$path);
    }

    //! Constructor
    function __construct() {
        $f3=\Base::instance();
        $config=(array)$f3->get('ACCESS');
        if (isset($config['policy']))
            $this->policy($config['policy']);
        if (isset($config['rules']))
            foreach((array)$config['rules'] as $str=>$subjects) {
                foreach(array(self::DENY,self::ALLOW) as $k=>$policy)
                    if (stripos($str,$policy)===0)
                        $this->rule((bool)$k,substr($str,strlen($policy)),$subjects);
            }
    }

}