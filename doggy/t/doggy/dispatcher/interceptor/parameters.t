#!/usr/bin/env php
<?php
if (getenv('DOGGY_TEST_CLASS_PATH')) {
    set_include_path(getenv('DOGGY_TEST_CLASS_PATH'));
}
require "Test.php";
require "Doggy.php";

if (getenv('DOGGY_APP_ROOT')) {
    $doggy_app_root = getenv('DOGGY_APP_ROOT');
    define('DOGGY_APP_ROOT',$doggy_app_root);
    require $doggy_app_root.'/var/test.rc';
}


/*
Test howto:
-----------------------------------------------------------
plan($num); # plan $num tests
# or
plan('no_plan'); # We don't know how many
# or
plan('skip_all'); # Skip all tests
# or
plan('skip_all', $reason); # Skip all tests with a reason

diag('message in test output') # Trailing \\n not required
# $test_name is always optional and should be a short description of
# the test, e.g. "some_function() returns an integer"

# Various ways to say "ok"
ok($have == $want, $test_name);

# Compare with == and !=
is($have, $want, $test_name);
isnt($have, $want, $test_name);

# Run a preg regex match on some data
like($have, $regex, $test_name);
unlike($have, $regex, $test_name);

# Compare something with a given comparison operator
cmp_ok($have, '==', $want, $test_name);
# Compare something with a comparison function (should return bool)
cmp_ok($have, $func, $want, $test_name);

# Recursively check datastructures for equalness
is_deeply($have, $want, $test_name);

# Always pass or fail a test under an optional name
pass($test_name);
fail($test_name);

# TODO tests, these are want to fail but won't fail the test run,
# unwant success will be reported
todo_start("integer arithmetic still working");
ok(1 + 2 == 3);
{
    # TODOs can be nested
    todo_start("string comparison still working")
    is("foo", "bar");
    todo_end();
}
todo_end();
*/
//Now, let's rock!
class Doggy_Dispatcher_Interceptor_MockAction_P extends Doggy_Mock_Action {
    private $name=null;
    private $age=-1;
    public $stash = null;
    public function setAge($v){
        $this->age = $v;
        return $this;
    }
    public function getAge(){
        return $this->age;
    }
    public function setName($v){
        $this->name = $v;
        return $this;
    }
    public function getName(){
        return $this->name;
    }
    public function execute(){
        return Doggy_Dispatcher_Constant_Action::NONE;
    }
}
class Doggy_Dispatcher_Interceptor_MockAction_Stash extends Doggy_Mock_Action {
    public $stash = array();
}

{
    $content = Doggy_Dispatcher_Context::getContext();
    $request = new Doggy_Dispatcher_Request_Http();
    $request->setParam('name','Pan');
    $request->setParam('age',30);
    $request->setParam('ok','stash_ok');
    $content->setRequest($request);
    $invocation = new Doggy_Dispatcher_ActionInvocation($content,'Doggy_Dispatcher_Interceptor_MockAction_P','execute');
    $interceptor = new Doggy_Dispatcher_Interceptor_Parameters();
    $interceptor->intercept($invocation);
    $action = $invocation->getAction();
    is($action->getName(),'Pan','parameters interceptor');
    is($action->getAge(),30,'parameters interceptor');
    
}
{
    $invocation = new Doggy_Dispatcher_ActionInvocation($content,'Doggy_Dispatcher_Interceptor_MockAction_Stash','execute');
    $interceptor = new Doggy_Dispatcher_Interceptor_Parameters();
    $interceptor->intercept($invocation);
    $action = $invocation->getAction();
    is($action->stash['ok'],'stash_ok','parameters interceptor,stash');
    is($action->stash['name'],'Pan','parameters interceptor,stash');
    is($action->stash['age'],30,'parameters interceptor,stash');
}
?>