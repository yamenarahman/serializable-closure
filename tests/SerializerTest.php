<?php

use Laravel\SerializableClosure\SerializableClosure;
use Laravel\SerializableClosure\Serializers\Signed;
use Laravel\SerializableClosure\Support\ReflectionClosure;
use Tests\Fixtures\Model;

test('closure use return value', function () {
    $a = 100;
    $c = function () use ($a) {
        return $a;
    };

    $u = s($c);

    expect($a)->toEqual($u());
})->with('serializers');

test('closure use transformation with Native', function () {
    $a = 100;

    SerializableClosure::transformUseVariablesUsing(function ($data) {
        foreach ($data as $key => $value) {
            $data[$key] = $value * 2;
        }

        return $data;
    });

    SerializableClosure::resolveUseVariablesUsing(function ($data) {
        foreach ($data as $key => $value) {
            $data[$key] = $value / 4;
        }

        return $data;
    });

    $c = unserialize(serialize(new SerializableClosure(function () use ($a) {
        return $a;
    })));

    expect($c())->toEqual(50);
})->skip((float) phpversion() < '7.4');

test('closure use transformation with Signed', function () {
    $a = 100;

    SerializableClosure::setSecretKey('secret');
    SerializableClosure::transformUseVariablesUsing(function ($data) {
        foreach ($data as $key => $value) {
            $data[$key] = $value * 2;
        }

        return $data;
    });

    SerializableClosure::resolveUseVariablesUsing(function ($data) {
        foreach ($data as $key => $value) {
            $data[$key] = $value / 4;
        }

        return $data;
    });

    $c = unserialize(serialize(new SerializableClosure(function () use ($a) {
        return $a;
    })));

    expect($c())->toEqual(50);
})->skip((float) phpversion() < '7.4');

test('closure use return closure', function () {
    $a = function ($p) {
        return $p + 1;
    };
    $b = function ($p) use ($a) {
        return $a($p);
    };

    $v = 1;
    $u = s($b);

    expect($u(1))->toEqual($v + 1);
})->with('serializers');

test('closure use return closure by ref', function () {
    $a = function ($p) {
        return $p + 1;
    };
    $b = function ($p) use (&$a) {
        return $a($p);
    };

    $v = 1;
    $u = s($b);

    expect($u(1))->toEqual($v + 1);
})->with('serializers');

test('closure use self', function () {
    $a = function () use (&$a) {
        return $a;
    };
    $u = s($a);

    expect($u())->toEqual($u);
})->with('serializers');

test('closure use self in array', function () {
    $a = [];

    $b = function () use (&$a) {
        return $a[0];
    };

    $a[] = $b;

    $u = s($b);

    expect($u())->toEqual($u);
})->with('serializers');

test('closure use self in object', function () {
    $a = new stdClass();

    $b = function () use (&$a) {
        return $a->me;
    };

    $a->me = $b;

    $u = s($b);

    expect($u())->toEqual($u);
})->with('serializers');

test('closure use self in multi array', function () {
    $a = [];
    $x = null;

    $b = function () use (&$x) {
        return $x;
    };

    $c = function ($i) use (&$a) {
        $f = $a[$i];

        return $f();
    };

    $a[] = $b;
    $a[] = $c;
    $x = $c;

    $u = s($c);

    expect($u(0))->toEqual($u);
})->with('serializers');

test('closure use self in instance', function () {
    $i = new ObjSelf();
    $c = function ($c) use ($i) {
        return $c === $i->o;
    };
    $i->o = $c;
    $u = s($c);
    expect($u($u))->toBeTrue();
})->with('serializers');

test('closure use self in instance2', function () {
    $i = new ObjSelf();
    $c = function () use (&$c, $i) {
        return $c == $i->o;
    };
    $i->o = &$c;
    $u = s($c);
    expect($u())->toBeTrue();
})->with('serializers');

test('closure serialization twice', function () {
    $a = function ($p) {
        return $p;
    };

    $b = function ($p) use ($a) {
        return $a($p);
    };

    $u = s(s($b));

    expect($u('ok'))->toEqual('ok');
})->with('serializers');

test('closure real serialization', function () {
    $f = function ($a, $b) {
        return $a + $b;
    };

    $u = s(s($f));
    expect($u(2, 3))->toEqual(5);
})->with('serializers');

test('closure nested', function () {
    $o = function ($a) {

        // this should never happen
        if ($a === false) {
            return false;
        }

        $n = function ($b) {
            return ! $b;
        };

        $ns = s($n);

        return $ns(false);
    };

    $os = s($o);

    expect($os(true))->toEqual(true);
})->with('serializers');

test('closure curly syntax', function () {
    $f = function () {
        $x = (object) ['a' => 1, 'b' => 3];
        $b = 'b';

        return $x->{'a'} + $x->{$b};
    };
    $f = s($f);
    expect($f())->toEqual(4);
})->with('serializers');

test('closure bind to object', function () {
    $a = new A();

    $b = function () {
        return $this->aPublic();
    };

    $b = $b->bindTo($a, __NAMESPACE__.'\\A');

    $u = s($b);

    expect($u())->toEqual('public called');
})->with('serializers');

test('closure bind to object scope', function () {
    $a = new A();

    $b = function () {
        return $this->aProtected();
    };

    $b = $b->bindTo($a, __NAMESPACE__.'\\A');

    $u = s($b);

    expect($u())->toEqual('protected called');
})->with('serializers');

test('closure bind to object static scope', function () {
    $a = new A();

    $b = function () {
        return static::aStaticProtected();
    };

    $b = $b->bindTo(null, __NAMESPACE__.'\\A');

    $u = s($b);

    expect($u())->toEqual('static protected called');
})->with('serializers');

test('closure static', function () {
    $f = static function () {
    };
    $rc = new ReflectionClosure($f);
    expect($rc->isStatic())->toBeTrue();
})->with('serializers');

test('closure static fail', function () {
    $f = static // This will not work
    function () {
    };
    $rc = new ReflectionClosure($f);
    expect($rc->isStatic())->toBeFalse();
})->with('serializers');

test('closure scope remains the same', function () {
    $f = function () {
        static $i = 0;
    };
    $o = s($f);

    $rf = new ReflectionClosure($f);
    $ro = new ReflectionClosure($o);

    test()->assertNotNull($rf->getClosureScopeClass());
    test()->assertNotNull($ro->getClosureScopeClass());
    expect($ro->getClosureScopeClass()->name)->toEqual($rf->getClosureScopeClass()->name);
    expect($ro->getClosureScopeClass()->name)->toEqual($rf->getClosureScopeClass()->name);

    $f = $f->bindTo(null, null);
    $o = s($f);
    $rf = new ReflectionClosure($f);
    $ro = new ReflectionClosure($o);

    expect($rf->getClosureScopeClass())->toBeNull();
    expect($ro->getClosureScopeClass())->toBeNull();
})->with('serializers');

test('mixed encodings', function () {
    $a = iconv('utf-8', 'utf-16', 'Düsseldorf');
    $b = utf8_decode('Düsseldorf');

    $closure = function () use ($a, $b) {
        return [$a, $b];
    };

    $u = s($closure);
    $r = $u();

    expect($r[0])->toEqual($a);
    expect($r[1])->toEqual($b);
})->with('serializers');

test('serialization string content dont change', function () {
    $a = 100;

    SerializableClosure::setSecretKey('foo');

    $c = new SerializableClosure(function () use ($a) {
        return $a;
    });

    $actual = explode('s:32:', serialize($c))[0];

    expect($actual)->toBe(<<<OEF
O:47:"Laravel\SerializableClosure\SerializableClosure":1:{s:12:"serializable";O:46:"Laravel\SerializableClosure\Serializers\Signed":2:{s:12:"serializable";s:264:"O:46:"Laravel\SerializableClosure\Serializers\Native":5:{s:3:"use";a:1:{s:1:"a";i:100;}s:8:"function";s:47:"function () use (\$a) {
        return \$a;
    }";s:5:"scope";s:22:"P\Tests\SerializerTest";s:4:"this";N;s:4:"self";
OEF
    );
});

test('use objects with serializable closures properties', function () {
    $a = new stdClass();

    if ($this->serializer == Signed::class) {
        SerializableClosure::setSecretKey('secret');
    }

    $a->b = new SerializableClosure(function () {
        return 'Hi';
    });

    $closure = function () use ($a) {
        return ($a->b)();
    };

    $u = s($closure);
    $r = $u();

    expect($r)->toEqual('Hi');
})->with('serializers');

test('rebound closure', function () {
    $closure = Closure::bind(
        function () {
            return $this->hello();
        },
        new A3(function () {
            return 'Hi';
        }),
        A3::class
    );

    $u = s($closure);
    $r = $u();

    expect($r)->toEqual('Hi');
})->with('serializers');

test('from callable namespaces', function () {
    $f = Closure::fromCallable([new Model, 'make']);

    $f = s($f);

    expect($f(new Model))->toBeInstanceOf(Model::class);
})->with('serializers');

test('from static callable namespaces', function () {
    $f = Closure::fromCallable([new Model, 'staticMake']);

    $f = s($f);

    expect($f(new Model))->toBeInstanceOf(Model::class);
})->with('serializers');

test('serializes datetime objects', function () {
    $closure = function () {
        return new DateTime('now');
    };
    $u = s($closure);
    $r = $u();

    expect($r)->toBeInstanceOf(DateTime::class);
})->with('serializers');

test('serializes formatted datetime objects', function () {
    $closure = function () {
        return date('Y-m-d');
    };
    $u = s($closure);
    $r = $u();

    expect($r)->toEqual(date('Y-m-d'));
})->with('serializers');

class A
{
    protected static function aStaticProtected()
    {
        return 'static protected called';
    }

    protected function aProtected()
    {
        return 'protected called';
    }

    public function aPublic()
    {
        return 'public called';
    }
}

class A2
{
    private $phrase = 'Hello, World!';
    private $closure1;
    private $closure2;
    private $closure3;

    public function __construct()
    {
        $this->closure1 = function () {
            return $this->phrase;
        };
        $this->closure2 = function () {
            return $this;
        };
        $this->closure3 = function () {
            $c = $this->closure2;

            return $this === $c();
        };
    }

    public function getPhrase()
    {
        $c = $this->closure1;

        return $c();
    }

    public function getEquality()
    {
        $c = $this->closure3;

        return $c();
    }
}

class A3
{
    private $closure;

    public function __construct($closure)
    {
        $this->closure = $closure;
    }

    public function hello()
    {
        return ($this->closure)();
    }
}

class ObjSelf
{
    public $o;
}
