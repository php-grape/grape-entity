<?php

namespace PhpGrape\Tests;

use PHPUnit\Framework\TestCase;

use PhpGrape\Entity;
use PhpGrape\EntityTrait;
use PhpGrape\Reflection;
use PhpGrape\Exceptions\InvalidOptionException;
use PhpGrape\Exceptions\InvalidTypeException;
use PhpGrape\Exceptions\MissingPropertyException;
use PhpGrape\Exceptions\NestedExposureException;

class EmbeddedExample
{
    public function serializableArray()
    {
        return ['abc' => 'def'];
    }
};

class EmptyEntity extends Entity
{
    use EntityTrait;
    private static function initialize()
    {
    }
}

class UserEntity extends Entity
{
    use EntityTrait;
    private static function initialize()
    {
        self::expose('id', 'name');
    }
}

class AdminEntity extends Entity
{
    use EntityTrait;
    private static function initialize()
    {
        self::expose('id', 'name');
    }
}

class PersonEntity extends Entity
{
    use EntityTrait;

    private static function initialize()
    {
        self::expose('user', function () {
            self::expose('inFirst', function ($_) {
                return 'value';
            });
        });
    }
}

class StudentEntity extends Entity
{
    use EntityTrait;

    private static function initialize()
    {
        self::extends(PersonEntity::class);

        self::expose('user', function () {
            self::expose('userId', function ($_) {
                return 'value';
            });
            self::expose('userDisplayId', ['as' => 'displayId'], function ($_) {
                return 'value';
            });
        });
    }
}

class ParentEntity extends Entity
{
    use EntityTrait;

    private static function initialize()
    {
        self::extends(PersonEntity::class);
        self::expose('children', ['using' => StudentEntity::class], function ($_) {
            return [[], []];
        });
    }
}

class ClassRoomEntity extends Entity
{
    use EntityTrait;

    private static function initialize()
    {
        self::expose('parents', ['using' => ParentEntity::class], function ($_) {
            return [[], []];
        });
    }
}


class TestAb
{
    public $a;
    private $b;

    public function b()
    {
        return $this->b;
    }

    public function __construct($props)
    {
        $this->a = $props['a'];
        $this->b = $props['b'];
    }
};

class TestAbCall
{
    private $props;

    public function __construct($props)
    {
        $this->props = $props;
    }

    public function __call($name, $args)
    {
        if (isset($this->props[$name])) return $this->props[$name];
        throw new \Exception("Property $name not found");
    }
};

class TestAbGet
{
    private $props;

    public function __construct($props)
    {
        $this->props = $props;
    }

    public function __get($name)
    {
        if (isset($this->props[$name])) return $this->props[$name];
        throw new \Exception("Property $name not found");
    }

    public function __set($name, $value)
    {
        $this->props[$name] = $value;
    }
};

class TestAbEntity extends Entity
{
    use EntityTrait;
    private static function initialize()
    {
    }

    public $a = 'aaa';
    public function b()
    {
        return 'bbb';
    }
};

class TestEntity extends Entity
{
    use EntityTrait;
    private static function initialize()
    {
    }
};


class TestBogusEntity extends Entity
{
    use EntityTrait;

    private static function initialize()
    {
        self::expose('prop1');
    }
};

function path($_, $o)
{
    return join('/', $o['attr_path']);
};

class PathEmailEntity extends Entity
{
    use EntityTrait;
    private static function initialize()
    {
        self::expose('email', ['as' => 'addr'], function ($_, $o) {
            return join('/', $o['attr_path']);
        });
    }
};
class PathUserEntity extends Entity
{
    use EntityTrait;
    private static function initialize()
    {
        self::expose('name', ['as' => 'full_name'], function ($_, $o) {
            return join('/', $o['attr_path']);
        });
        self::expose('email', ['using' => PathEmailEntity::class]);
    }
};
class PathExtraEntity extends Entity
{
    use EntityTrait;
    private static function initialize()
    {
        self::expose('key', function ($_, $o) {
            return join('/', $o['attr_path']);
        });
        self::expose('value', function ($_, $o) {
            return join('/', $o['attr_path']);
        });
    }
};
class PathNestedEntity extends Entity
{
    use EntityTrait;

    private static function initialize()
    {
        self::expose('name', function ($_, $o) {
            return join('/', $o['attr_path']);
        });
        self::expose('data', ['using' => PathExtraEntity::class]);
    }
};

class EntityTest extends TestCase
{
    /**
     * @before
     */
    public function reset(): void
    {
        TestEntity::$rootExposures = [];
        TestEntity::$formatters = [];

        $defaultValues = ["initialized" => false, "defaultOptions" => [], "documentation" => null, "countNestedExposures" => 0, "presentCollection" => false, "collectionName" => "items", 'collectionRoot' => null, "root" => null];
        foreach ($defaultValues as $key => $value) {
            $property = new \ReflectionProperty(TestEntity::class, $key);
            $property->setAccessible(true);
            $property->setValue($value);
        }

        Entity::$globalFormatters = [];

        Reflection::$disableProtectedProps = false;
        Reflection::$disablePrivateProps = false;
        Reflection::$disableProtectedMethods = false;
        Reflection::$disablePrivateMethods = false;

        Entity::transformKeys(null);
    }

    public function testExposeAssociativeArray()
    {
        $array = ['name' => 'foo', 'email' => function () {
            return 'foo@bar.com';
        }];
        TestEntity::expose('name');
        TestEntity::expose('email');
        $representation = TestEntity::represent($array);
        $this->assertEquals(['name' => 'foo', 'email' => 'foo@bar.com'], $representation->serializableArray());
    }

    public function testExposeObject()
    {
        $array = ['a' => 'aaa', 'b' => 'bbb'];
        TestEntity::expose('a');
        TestEntity::expose('b');

        $representation = TestEntity::represent(new TestAb($array));
        $this->assertEquals($array, $representation->serializableArray());
    }

    public function testExposeWithEntityOverride()
    {
        $array = ['a' => 'a'];
        TestAbEntity::expose('a');
        TestAbEntity::expose('b');
        $representation = TestAbEntity::represent($array);
        $this->assertEquals(['a' => 'aaa', 'b' => 'bbb'], $representation->serializableArray());
    }


    public function testExposeObjectWithMagicCall()
    {
        $array = ['a' => 'aaa', 'b' => 'bbb'];
        TestEntity::expose('a');
        TestEntity::expose('b');
        $representation = TestEntity::represent(new TestAbCall($array));
        $this->assertEquals($array, $representation->serializableArray());
    }

    public function testExposeObjectWithMagicGet()
    {
        $array = ['a' => 'aaa', 'b' => 'bbb'];
        TestEntity::expose('a');
        TestEntity::expose('b');
        $representation = TestEntity::represent(new TestAbGet($array));
        $this->assertEquals($array, $representation->serializableArray());
    }

    public function testExposeMultipleAttributes()
    {
        TestEntity::expose('name', 'email', 'location');
        $this->assertEquals(count(TestEntity::$rootExposures), 3);
    }

    public function testExposeSameOptionForAllAttributes()
    {
        TestEntity::expose('name', 'email', 'birthday', ['safe' => true, 'documentation' => ['desc' => 'foo']]);
        foreach (TestEntity::$rootExposures as $exposure) {
            $this->assertEquals(['safe' => true, 'documentation' => ['desc' => 'foo']], $exposure['options']);
        }
    }

    public function testExposeAsOptionValidation()
    {
        // makes sure that `as` only works on single attribute calls
        $this->expectException(InvalidOptionException::class);
        TestEntity::expose('firstname', 'email', ['as' => 'foo']);

        // makes sure it doesn't throw an exception
        $this->assertNull(TestEntity::expose('firstname', ['as' => 'foo']));
    }

    public function testExposeFormatWithOptionValidation()
    {
        // makes sure that `format_with` cannot be used on a nested exposure
        $this->expectException(InvalidOptionException::class);
        TestEntity::expose('contact', ['format_with' => function () {
        }], function () {
        });
        $this->expectException(InvalidOptionException::class);
        TestEntity::expose('contact', ['format_with' => 'foo'], function () {
        });

        // makes sure it doesn't throw an exception
        $this->assertNull(TestEntity::expose('name', ['format_with' => 'foo']));
        $this->assertNull(TestEntity::expose('name', ['format_with' => function () {
        }]));
    }

    public function testExposeUnknownOptionValidation()
    {
        // makes sure unknown options are not silently ignored
        $this->expectException(InvalidOptionException::class);
        TestEntity::expose('contact', ['unknown' => null]);
    }

    public function testExposeMergeToTheRoot()
    {
        // merges an exposure to the root
        $nestedArray = [
            'something' => ['like_nested_array' => true],
            'special' => ['like_nested_array' => '12']
        ];
        TestEntity::expose('something', ['merge' => true]);
        $representation = TestEntity::represent($nestedArray);
        $this->assertEquals($nestedArray['something'], $representation->serializableArray());
    }

    public function testExposeMergeCollisions()
    {
        // allows to solve collisions providing a function to a `merge` option
        $nestedArray = [
            'something' => ['like_nested_array' => true],
            'special' => ['like_nested_array' => '12']
        ];
        TestEntity::expose('something', ['merge' => true]);
        TestEntity::expose('special', ['merge' => function ($_, $v1, $v2) {
            return $v1 && $v2 ? 'brand new val' : $v2;
        }]);
        $representation = TestEntity::represent($nestedArray);
        $this->assertEquals(['like_nested_array' => 'brand new val'], $representation->serializableArray());
    }

    public function testExposeMergeWithNestedObjectNull()
    {
        // adds nothing to outputœ
        $nestedArray = [
            'something' => null,
            'special' => ['likeNestedArray' => '12']
        ];
        TestEntity::expose('something', ['merge' => true]);
        TestEntity::expose('special');
        $representation = TestEntity::represent($nestedArray);
        $this->assertEquals(['special' => ['likeNestedArray' => '12']], $representation->serializableArray());
    }

    public function testExposeMergeNonAssociativeArray()
    {
        // adds nothing to output
        $nestedArray = [
            'something' => 12,
            'special' => ['likeNestedArray' => '12']
        ];
        TestEntity::expose('something', ['merge' => true]);
        TestEntity::expose('special');

        $this->expectException(InvalidTypeException::class);
        TestEntity::represent($nestedArray)->serializableArray();
    }

    public function testExposeExposeNullNotProvided()
    {
        // adds nothing to output
        $props = ['a' => null, 'b' => null, 'c' => 'value'];
        TestEntity::expose('a');
        TestEntity::expose('b');
        TestEntity::expose('c');
        $representation = TestEntity::represent($props);
        $this->assertEquals($props, $representation->serializableArray());
    }

    public function testExposeExposeNullTrue()
    {
        // when expose_null option is true
        $props = ['a' => null, 'b' => null, 'c' => 'value'];
        TestEntity::expose('a', ['expose_null' => true]);
        TestEntity::expose('b', ['expose_null' => true]);
        TestEntity::expose('c');
        $representation = TestEntity::represent($props);
        $this->assertEquals($props, $representation->serializableArray());
    }

    public function testExposeExposeNullFalse()
    {
        // when expose_null option is false
        $props = ['a' => null, 'b' => null, 'c' => 'value'];
        TestEntity::expose('a', ['expose_null' => false]);
        TestEntity::expose('b', ['expose_null' => false]);
        TestEntity::expose('c');
        $representation = TestEntity::represent($props);
        $this->assertEquals(['c' => 'value'], $representation->serializableArray());
    }

    public function testExposeExposeNullFalseOneAttribute()
    {
        // is only applied per attribute
        $props = ['a' => null, 'b' => null, 'c' => 'value'];
        TestEntity::expose('a', ['expose_null' => false]);
        TestEntity::expose('b');
        TestEntity::expose('c');
        $representation = TestEntity::represent($props);
        $this->assertEquals(['b' => null, 'c' => 'value'], $representation->serializableArray());
    }

    public function testExposeExposeNullToMultipleAttributes()
    {
        // throws an exception when applied to multiple attribute exposures
        $this->expectException(InvalidOptionException::class);
        TestEntity::expose('a', 'b', 'c', ['expose_null' => false]);
    }

    public function testExposeExposeNullAndFunctionWithNullValue()
    {
        // does not expose if function returns null
        $props = ['a' => null, 'b' => null, 'c' => 'value'];
        TestEntity::expose('a', ['expose_null' => false], function ($obj, $options) {
            return null;
        });
        TestEntity::expose('b');
        TestEntity::expose('c');
        $representation = TestEntity::represent($props);
        $this->assertEquals(['b' => null, 'c' => 'value'], $representation->serializableArray());
    }

    public function testExposeExposeNullAndFunctionWithValue()
    {
        // exposes is function returns a value
        $props = ['a' => null, 'b' => null, 'c' => 'value'];
        TestEntity::expose('a', ['expose_null' => false], function ($obj, $options) {
            return 100;
        });
        TestEntity::expose('b');
        TestEntity::expose('c');
        $representation = TestEntity::represent($props);
        $this->assertEquals(['a' => 100, 'b' => null, 'c' => 'value'], $representation->serializableArray());
    }

    public function testExposeDefaultSet()
    {
        // exposes default values for attributes
        $props = ['a' => null, 'b' => null, 'c' => 'value'];
        TestEntity::expose('a', ['default' => 'a']);
        TestEntity::expose('b', ['default' => 'b']);
        TestEntity::expose('c', ['default' => 'c']);
        $representation = TestEntity::represent($props);
        $this->assertEquals(['a' => 'a', 'b' => 'b', 'c' => 'value'], $representation->serializableArray());
    }

    public function testExposeDefaultSetAndFunctionPassed()
    {
        // exposes default values for attributes
        $props = ['a' => null, 'b' => null, 'c' => 'value'];
        TestEntity::expose('a', ['default' => 'a'], function ($object, $options) {
            return null;
        });
        TestEntity::expose('b');
        TestEntity::expose('c');
        $representation = TestEntity::represent($props);
        $this->assertEquals(['a' => 'a', 'b' => null, 'c' => 'value'], $representation->serializableArray());
    }

    public function testExposeDefaultSetAndFunctionReturningValue()
    {
        // exposes default values for attributes
        $props = ['a' => null, 'b' => null, 'c' => 'value'];
        TestEntity::expose('a', ['default' => 'a'], function ($object, $options) {
            return 100;
        });
        TestEntity::expose('b');
        TestEntity::expose('c');
        $representation = TestEntity::represent($props);
        $this->assertEquals(['a' => 100, 'b' => null, 'c' => 'value'], $representation->serializableArray());
    }

    public function testExposeWithFunctionAndMultipleAttributes()
    {
        // throw an exception if called with multiple attributes
        $this->expectException(InvalidOptionException::class);
        TestEntity::expose('name', 'email', function ($object, $options) {
            return true;
        });
    }

    public function testExposeUsingOption()
    {
        // references an instance of the entity with :using option
        TestEntity::expose('bogus', ['using' => TestBogusEntity::class]);
        $representation = TestEntity::represent(['a' => null, 'bogus' => ['prop1' => 'value1', 'prop2' => 'value2']]);
        $this->assertEquals(['bogus' => ['prop1' => 'value1']], $representation->serializableArray());

        $representation = TestEntity::represent(['a' => null, 'bogus' => ['prop1' => 'value1', 'prop2' => 'value2']]);
    }

    public function testExposeUsingOptionWithAnonymousClass()
    {
        TestEntity::expose('bogus', ['using' => new class([]) extends Entity
        {
            use EntityTrait;
            public static function initialize()
            {
                self::expose('prop2');
            }
        }]);
        $representation = TestEntity::represent(['a' => null, 'bogus' => ['prop1' => 'value1', 'prop2' => 'value2']]);
        $this->assertEquals(['bogus' => ['prop2' => 'value2']], $representation->serializableArray());

        $representation = TestEntity::represent(['a' => null, 'bogus' => ['prop1' => 'value1', 'prop2' => 'value2']]);
    }

    public function testExposeUsingOptionWithFunction()
    {
        // It exposes attributes that don't exist on the object only when they are generated by a block with options
        TestEntity::expose('nonExistentProperty', ['using' => EmptyEntity::class], function ($_) {
            return "value2";
        });
        $this->assertEquals(['nonExistentProperty' => []], (new TestEntity([['name' => 'John']]))->serializableArray());
    }

    public function testExposeUsingOptionWithArrayAndFunction()
    {
        // references an instance of the entity with :using option
        TestEntity::expose('bogus', ['using' => TestBogusEntity::class], function ($object, $options) {
            $object['prop1'] = 'MODIFIED 2';
            return $object;
        });
        $representation = TestEntity::represent(['bogus' => ['prop1' => 'value1']]);
        $this->assertEquals(['bogus' => ['prop1' => 'MODIFIED 2']], $representation->serializableArray());
    }

    public function testExposeUsingOptionWithObjectAndFunction()
    {
        // references an instance of the entity with :using option
        TestEntity::expose('b', ['using' => TestBogusEntity::class], function ($object, $options) {
            $object->prop1 = 'MODIFIED 2';
            return $object;
        });
        $ab = new TestAb(['a' => 'a', 'b' => new TestAbGet(['prop1' => 'value1'])]);
        $representation = TestEntity::represent($ab);
        $this->assertEquals(['b' => ['prop1' => 'MODIFIED 2']], $representation->serializableArray());
    }

    public function testExposeWithFunction()
    {
        // with function passed in
        TestEntity::expose('thatMethodWithoutArgs', function ($object) {
            return $object->methodWithoutArgs();
        });
        $representation = TestEntity::represent(new class
        {
            function methodWithoutArgs()
            {
                return 'result';
            }
        });
        $this->assertEquals(['thatMethodWithoutArgs' => 'result'], $representation->serializableArray());
    }

    public function testExposeWithNestedExposure1()
    {
        // nested exposure
        TestEntity::expose('awesome', function () {
            TestEntity::expose('nested', function () {
                TestEntity::expose('moarNested', ['as' => 'weee']);
            });
            TestEntity::expose('anotherNested', ['using' => TestBogusEntity::class]);
        });
        $representation = TestEntity::represent(['moarNested' => 'nested', 'anotherNested' => ['prop1' => 'value1', 'prop2' => 'value2']]);

        $this->assertEquals(['awesome' => [
            'nested' => ['weee' => 'nested'],
            'anotherNested' => ['prop1' => 'value1']
        ]], $representation->serializableArray());
    }

    public function testExposeWithNestedExposure2()
    {
        // represents the exposure as an associative array of its nested root exposures
        TestEntity::expose('awesome', function () {
            TestEntity::expose('nested', function ($_) {
                return 'value';
            });
            TestEntity::expose('anotherNested', function ($_) {
                return 'value';
            });
            TestEntity::expose('secondLevelNested', function () {
                TestEntity::expose('deeplyExposedAttr', function ($_) {
                    return 'value';
                });
            });
        });
        $representation = TestEntity::represent([]);
        $this->assertEquals(['awesome' => [
            'nested' => 'value',
            'anotherNested' => 'value',
            'secondLevelNested' => [
                'deeplyExposedAttr' => 'value'
            ]
        ]], $representation->serializableArray());
    }

    public function testExposeWithNestedExposureAndConditions()
    {
        // does not represent nested root exposures whose conditions are not met
        TestEntity::expose('awesome', function () {
            TestEntity::expose('conditionMet', ['if' => function () {
                return true;
            }], function ($_) {
                return 'value';
            });
            TestEntity::expose('conditionNotMet', ['if' => function () {
                return false;
            }], function ($_) {
                return 'value';
            });
        });
        $representation = TestEntity::represent([]);
        $this->assertEquals(['awesome' => ['conditionMet' => 'value']], $representation->serializableArray());
    }

    public function testMergeComplexNestedAttributes()
    {
        $representation = ClassRoomEntity::represent([], ['serializable' => true]);
        $this->assertEquals([
            'parents' => [
                [
                    'user' => ['inFirst' => 'value'],
                    'children' => [
                        ['user' => ['inFirst' => 'value', 'userId' => 'value', 'displayId' => 'value']],
                        ['user' => ['inFirst' => 'value', 'userId' => 'value', 'displayId' => 'value']]
                    ]
                ],
                [
                    'user' => ['inFirst' => 'value'],
                    'children' => [
                        ['user' => ['inFirst' => 'value', 'userId' => 'value', 'displayId' => 'value']],
                        ['user' => ['inFirst' => 'value', 'userId' => 'value', 'displayId' => 'value']]
                    ]
                ]
            ]
        ], $representation);
    }

    public function testMergeDoubleRootExposures()
    {
        // merges results of deeply nested double rootExposures inside of nesting exposure
        TestEntity::expose('data', function () {
            TestEntity::expose('something', function () {
                TestEntity::expose('x', function ($_) {
                    return 'x';
                });
            });
            TestEntity::expose('something', function () {
                TestEntity::expose('y', function ($_) {
                    return 'y';
                });
            });
        });

        $representation = TestEntity::represent([]);
        $this->assertEquals(['data' => ['something' => ['x' => 'x', 'y' => 'y']]], $representation->serializableArray());
    }

    public function testDeeplyNestedPresenterExposures()
    {
        TestEntity::expose('a', function () {
            TestEntity::expose('b', function () {
                TestEntity::expose('c', function () {
                    TestEntity::expose('lol', ['using' => TestBogusEntity::class]);
                });
            });
        });

        $representation = TestEntity::represent(['lol' => ['prop1' => '123']]);
        $this->assertEquals(['a' => ['b' => ['c' => ['lol' => ['prop1' => '123']]]]], $representation->serializableArray());
    }

    public function testSafeRootExposures()
    {
        // it is safe if its nested rootExposures are safe
        TestEntity::withOptions(['safe' => true], function () {
            TestEntity::expose('awesome', function () {
                TestEntity::expose('nested', function ($_) {
                    return 'value';
                });
            });
            TestEntity::expose('notAwesome', function () {
                TestEntity::expose('nested');
            });
        });

        $representation = TestEntity::represent([]);
        $this->assertEquals([
            'awesome' => ['nested' => 'value'],
            'notAwesome' => ['nested' => null]
        ], $representation->serializableArray());
    }

    public function testMergeAttributes()
    {
        // merges attributes if `merge` option is passed
        TestEntity::expose('profiles', function () {
            TestEntity::expose('users', ['merge' => true, 'using' => UserEntity::class]);
            TestEntity::expose('admins', ['merge' => true, 'using' => AdminEntity::class]);
        });
        TestEntity::expose('awesome', function () {
            TestEntity::expose('nested', ['merge' => true], function ($_) {
                return ['justAKey' => 'value'];
            });
            TestEntity::expose('anotherNested', ['merge' => true], function ($_) {
                return ['justAnotherKey' => 'value'];
            });
        });

        $additionalArray = [
            'users' => [['id' => 1, 'name' => 'John'], ['id' => 2, 'name' => 'Jay']],
            'admins' => [['id' => 3, 'name' => 'Jack'], ['id' => 4, 'name' => 'James']],
        ];
        $representation = TestEntity::represent($additionalArray);
        $this->assertEquals([
            'profiles' => array_merge($additionalArray['users'], $additionalArray['admins']),
            'awesome' => ['justAKey' => 'value', 'justAnotherKey' => 'value']
        ], $representation->serializableArray());
    }

    public function testExtends()
    {
        TestEntity::extends(TestBogusEntity::class);
        TestEntity::expose('awesome', function ($_) {
            return 'value';
        });
        $representation = TestEntity::represent(['prop1' => 'value1']);
        $this->assertEquals(['prop1' => 'value1', 'awesome' => 'value'], $representation->serializableArray());
    }

    public function testMultipleExtends()
    {
        TestEntity::extends(TestBogusEntity::class, PersonEntity::class, UserEntity::class);
        TestEntity::expose('awesome', function ($_) {
            return 'value';
        });
        $representation = TestEntity::represent(['prop1' => 'value1', 'id' => 1, 'name' => 'John']);
        $this->assertEquals([
            'prop1' => 'value1',
            'user' => ['inFirst' => 'value'],
            'id' => 1, 'name' => 'John',
            'awesome' => 'value'
        ], $representation->serializableArray());
    }

    public function testExtendsExposureNotOverriden()
    {
        TestEntity::extends(TestBogusEntity::class);
        TestEntity::expose('prop1', ['as' => 'childProp1']);

        $representation = TestBogusEntity::represent(['prop1' => 'value1']);
        $this->assertEquals(['prop1' => 'value1'], $representation->serializableArray());

        $representation = TestEntity::represent(['prop1' => 'value1']);
        $this->assertEquals(['prop1' => 'value1', 'childProp1' => 'value1'], $representation->serializableArray());
    }

    public function testOverride()
    {
        TestEntity::extends(TestBogusEntity::class);
        TestEntity::expose('prop1', ['as' => 'prop2', 'override' => true]);
        $representation = TestEntity::represent(['prop1' => 'value1']);
        $this->assertEquals(['prop2' => 'value1'], $representation->serializableArray());
    }

    public function testFormatWith()
    {
        TestEntity::expose('prop', ['format_with' => function ($value) {
            return trim($value);
        }]);

        $representation = TestEntity::represent(['prop' => '  value  ']);
        $this->assertEquals(['prop' => 'value'], $representation->serializableArray());
    }

    public function testInvalidFormatWith()
    {
        function trimFormatter($value)
        {
            return trim($value);
        }

        TestEntity::expose('prop', ['format_with' => 'trimFormatter']);

        $this->expectException(InvalidOptionException::class);
        TestEntity::represent(['prop' => '  value  '])->serializableArray();
    }

    public function testFormatWithNotFound()
    {
        // Should not be global
        TestBogusEntity::formatWith('trimFormatter', function ($value) {
            return trim($value);
        });
        TestEntity::expose('prop', ['format_with' => 'trimFormatter']);

        $this->expectException(InvalidOptionException::class);
        TestEntity::represent(['prop' => '  value  '])->serializableArray();
    }

    public function testFormatWithRegistered()
    {
        TestEntity::formatWith('trimFormatter', function ($value) {
            return trim($value);
        });
        TestEntity::expose('prop', ['format_with' => 'trimFormatter']);

        $representation = TestEntity::represent(['prop' => '  value  ']);
        $this->assertEquals(['prop' => 'value'], $representation->serializableArray());
    }

    public function testGlobalFormatWith()
    {
        Entity::formatWith('trimFormatter', function ($value) {
            return trim($value);
        });
        TestEntity::expose('prop', ['format_with' => 'trimFormatter']);

        $representation = TestEntity::represent(['prop' => '  value  ']);
        $this->assertEquals(['prop' => 'value'], $representation->serializableArray());
    }

    public function testExtendsFormatWith()
    {
        TestBogusEntity::formatWith('trimFormatter', function ($value) {
            return trim($value);
        });
        TestEntity::extends(TestBogusEntity::class);
        TestEntity::expose('prop1', ['format_with' => 'trimFormatter']);

        $representation = TestEntity::represent(['prop1' => '  value  ']);
        $this->assertEquals(['prop1' => 'value'], $representation->serializableArray());
    }

    public function testFormatWithReturningValueFromEntity()
    {
        Entity::formatWith('entityGlobalFormatter', function () {
            return get_class($this->object) . '1';
        });
        TestEntity::formatWith('entityLocalFormatter', function () {
            return get_class($this->object) . '2';
        });
        TestEntity::expose('prop1', ['format_with' => 'entityGlobalFormatter']);
        TestEntity::expose('prop2', ['format_with' => 'entityLocalFormatter']);
        TestEntity::expose('prop3', ['format_with' => function () {
            return get_class($this->object) . '3';
        }]);

        $representation = TestEntity::represent(new TestAbGet(['prop1' => 'value1', 'prop2' => 'value2', 'prop3' => 'value3']));
        $this->assertEquals(
            [
                'prop1' => 'PhpGrape\Tests\TestAbGet1',
                'prop2' => 'PhpGrape\Tests\TestAbGet2',
                'prop3' => 'PhpGrape\Tests\TestAbGet3'
            ],
            $representation->serializableArray()
        );
    }

    public function testUnexpose()
    {
        TestEntity::expose('name', 'email');
        TestEntity::unexpose('email');

        $this->assertEquals(count(TestEntity::$rootExposures), 1);
        $this->assertEquals(TestEntity::$rootExposures[0]['prop'], 'name');
    }

    public function testUnexposeNotAllowedOnNestedExposure()
    {
        $this->expectException(NestedExposureException::class);

        TestEntity::expose('something', function () {
            TestEntity::expose('x');
            TestEntity::unexpose('x');
        });
    }

    public function testSimpleWithOptions()
    {
        TestEntity::withOptions(['if' => ['awesome' => true]], function () {
            TestEntity::expose('awesomeThing');
        });
        $representation = TestEntity::represent(['awesomeThing' => 'value'], ['awesome' => true]);
        $this->assertEquals(['awesomeThing' => 'value'], $representation->serializableArray());
    }

    public function testWithOptionsUnknownOption()
    {
        // it throws an exception for unknown options
        $this->expectException(InvalidOptionException::class);

        TestEntity::withOptions(['unknown' => true], function () {
            TestEntity::expose('awesomeThing');
        });
    }

    public function testWithOptionsNested()
    {
        TestEntity::withOptions(['if' => ['awesome' => true]], function () {
            TestEntity::withOptions(['format_with' => function ($value) {
                return trim($value);
            }], function () {
                TestEntity::expose('awesomeThing');
            });
        });
        $representation = TestEntity::represent(['awesomeThing' => '  value  '], ['awesome' => true]);
        $this->assertEquals(['awesomeThing' => 'value'], $representation->serializableArray());
    }

    public function testWithOptionsPropagatesIf()
    {
        // It propagates `if` option
        TestEntity::withOptions(['if' => ['awesome' => true]], function () {
            TestEntity::expose('awesomeThing');
        });
        $representation = TestEntity::represent(['awesomeThing' => 'value'], ['awesome' => true]);
        $this->assertEquals(['awesomeThing' => 'value'], $representation->serializableArray());
        $representation = TestEntity::represent(['awesomeThing' => 'value']);
        $this->assertEquals([], $representation->serializableArray());
    }

    public function testWithOptionsMergesNestedIfOption()
    {
        // It merges nested `if` option
        TestEntity::withOptions(['if' => 'awesome'], function () {
            TestEntity::withOptions(['if' => ['awesome' => true]], function () {
                TestEntity::withOptions(['if' => function () {
                    return true;
                }], function () {
                    TestEntity::withOptions(['if' => ['awesome' => false, 'lessAwesome' => true]], function () {
                        TestEntity::expose('awesomeThing');
                    });
                });
            });
        });
        $representation = TestEntity::represent(['awesomeThing' => 'value'], ['awesome' => false, 'lessAwesome' => true]);
        $this->assertEquals(['awesomeThing' => 'value'], $representation->serializableArray());
        $representation = TestEntity::represent(['awesomeThing' => 'value'], ['awesome' => true]);
        $this->assertEquals([], $representation->serializableArray());
    }

    public function testWithOptionsPropagatesAs()
    {
        // It propagates `as` option
        TestEntity::withOptions(['as' => 'sweet'], function () {
            TestEntity::expose('awesomeThing');
        });
        $representation = TestEntity::represent(['awesomeThing' => 'value']);
        $this->assertEquals(['sweet' => 'value'], $representation->serializableArray());
    }

    public function testWithOptionsOverridesAsOption()
    {
        // It overrides nested `as` option
        TestEntity::withOptions(['as' => 'sweet'], function () {
            TestEntity::expose('awesomeThing', ['as' => 'extraSmooth']);
        });
        $representation = TestEntity::represent(['awesomeThing' => 'value']);
        $this->assertEquals(['extraSmooth' => 'value'], $representation->serializableArray());
    }

    public function testWithOptionsPropagatesUsing()
    {
        // It propagates `using` option
        TestEntity::withOptions(['using' => TestBogusEntity::class], function () {
            TestEntity::expose('bogus');
        });
        $representation = TestEntity::represent(['bogus' => ['prop1' => 'value1']]);
        $this->assertEquals(['bogus' => ['prop1' => 'value1']], $representation->serializableArray());
    }

    public function testWithOptionsOverridesNestedUsingOption()
    {
        // It overrides nested `using` option
        TestEntity::withOptions(['using' => UserEntity::class], function () {
            TestEntity::expose('bogus', ['using' => TestBogusEntity::class]);
        });
        $representation = TestEntity::represent(['bogus' => ['prop1' => 'value1', 'id' => 1]]);
        $this->assertEquals(['bogus' => ['prop1' => 'value1']], $representation->serializableArray());
    }

    public function testWithOptionsUsingAlias()
    {
        // It aliases `with` option to `using` option
        TestEntity::withOptions(['using' => UserEntity::class], function () {
            TestEntity::expose('bogus', ['with' => TestBogusEntity::class]);
        });
        $representation = TestEntity::represent(['bogus' => ['prop1' => 'value1', 'id' => 1]]);
        $this->assertEquals(['bogus' => ['prop1' => 'value1']], $representation->serializableArray());
    }

    public function testWithOptionsPropagatesFunc()
    {
        // It propagates `func` option
        TestEntity::withOptions(['func' => function ($_) {
            return 'value2';
        }], function () {
            TestEntity::expose('awesomeThing');
        });
        $representation = TestEntity::represent(['awesomeThing' => 'value1']);
        $this->assertEquals(['awesomeThing' => 'value2'], $representation->serializableArray());
    }

    public function testWithOptionsOverridesNestedFuncOption()
    {
        // It overrides nested `function` option
        TestEntity::withOptions(['func' => function ($_) {
            return 'awesome';
        }], function () {
            TestEntity::expose('awesomeThing', ['func' => function ($_) {
                return 'more awesome';
            }]);
        });
        $representation = TestEntity::represent(['awesomeThing' => 'value']);
        $this->assertEquals(['awesomeThing' => 'more awesome'], $representation->serializableArray());
    }

    public function testWithOptionsPropagatesDocumentation()
    {
        // It propagates `documentation` option
        TestEntity::withOptions(['documentation' => ['desc' => 'foo']], function () {
            TestEntity::expose('awesomeThing');
        });
        $this->assertEquals(['awesomeThing' => ['desc' => 'foo']], TestEntity::documentation());
    }

    public function testWithOptionsOverridesDocumentationOption()
    {
        // It overrides nested `documentation` option
        TestEntity::withOptions(['documentation' => ['desc' => 'foo']], function () {
            TestEntity::expose('awesomeThing', ['documentation' => ['desc' => 'bar']]);
        });
        $this->assertEquals(['awesomeThing' => ['desc' => 'bar']], TestEntity::documentation());
    }

    public function testWithOptionsPropagatesExposeNull()
    {
        // It propagates `expose_null` option
        TestEntity::withOptions(['expose_null' => true], function () {
            TestEntity::expose('awesomeThing');
        });
        $this->assertEquals(['expose_null' => true], TestEntity::$rootExposures[0]['options']);
    }

    public function testWithOptionsOverridesExposeNullOption()
    {
        // It overrides nested `expose_null` option
        TestEntity::withOptions(['expose_null' => true], function () {
            TestEntity::expose('awesomeThing', ['expose_null' => false]);
            TestEntity::expose('otherAwesomeThing');
        });
        $this->assertEquals(['expose_null' => false], TestEntity::$rootExposures[0]['options']);
        $this->assertEquals(['expose_null' => true], TestEntity::$rootExposures[1]['options']);
    }

    public function testRepresentSingleEntityOneArray()
    {
        // It returns a single entity if called with one array
        TestEntity::expose('name');

        $representation = TestEntity::represent(['name' => 'John']);
        $this->assertEquals(TestEntity::class, get_class($representation));
        $this->assertEquals($representation->options['collection'], false);
    }

    public function testRepresentSingleEntityOneObject()
    {
        // It returns a single entity if called with one object
        TestEntity::expose('name');

        $representation = TestEntity::represent(new TestAbGet(['name' => 'John']));
        $this->assertEquals(TestEntity::class, get_class($representation));
        $this->assertEquals($representation->options['collection'], false);
    }

    public function testRepresentMultipleEntityOneCollection()
    {
        // It returns a single entity if called with one object
        TestEntity::expose('name');

        $representation = TestEntity::represent(array_fill(0, 4, new TestAbGet(['name' => 'John'])));
        $this->assertEquals(is_array($representation), true);
        $this->assertEquals(count($representation), 4);
        $this->assertEquals(array_fill(0, 4, TestEntity::class), array_map('get_class', $representation));
        $this->assertEquals(array_fill(0, 4, true), array_map(function ($entity) {
            return $entity->options['collection'];
        }, $representation));
    }

    public function testSerializeWithOneObject()
    {
        // It returns a serialized array of a single object if `serializable` option is true
        TestEntity::expose('name');

        $representation = TestEntity::represent(new TestAbGet(['name' => 'John']), ['serializable' => true]);
        $this->assertEquals(is_array($representation), true);
        $this->assertEquals(['name' => 'John'], $representation);
    }

    public function testSerializeWithOneArray()
    {
        // It returns a serialized array of an array if `serializable` option is true
        TestEntity::expose('name');

        $representation = TestEntity::represent(['name' => 'John'], ['serializable' => true]);
        $this->assertEquals(is_array($representation), true);
        $this->assertEquals(['name' => 'John'], $representation);
    }

    public function testSerializeWithMultipleObjects()
    {
        // It returns a serialized array of arrays of multiple objects if `serializable` option is true
        TestEntity::expose('name');

        $representation = TestEntity::represent(array_fill(0, 2, new TestAbGet(['name' => 'John'])), ['serializable' => true]);
        $this->assertEquals(is_array($representation), true);
        $this->assertEquals([['name' => 'John'], ['name' => 'John']], $representation);
    }

    public function testSerializeFieldNotFound()
    {
        // It throws an exception if field not found
        TestEntity::expose('name');

        $this->expectException(MissingPropertyException::class);
        TestEntity::represent(new TestAbGet([]), ['serializable' => true]);
    }

    public function testOnly()
    {
        // It returns only specified fields with `only` option
        TestEntity::expose('id', 'name', 'phone', 'city');
        $obj = new TestAbGet(['id' => 1, 'name' => 'John', 'phone' => '5551234514', 'city' => 'New York']);

        $representation = TestEntity::represent($obj, ['only' => ['id', 'name'], 'serializable' => true]);
        $this->assertEquals(is_array($representation), true);
        $this->assertEquals(['id' => 1, 'name' => 'John'], $representation);

        $representation = TestEntity::represent($obj, ['only' => 'id', 'serializable' => true]);
        $this->assertEquals(['id' => 1], $representation);
    }

    public function testOnlyNestedExposure()
    {
        // It can specify children attributes with `only` option
        TestEntity::expose('id', 'name', 'phone', 'city');
        TestEntity::expose('user', ['using' => UserEntity::class]);
        $arr = ['id' => 1, 'name' => 'John', 'phone' => '5551234514', 'city' => 'New York', 'user' => ['id' => 2, 'name' => 'Jane']];

        $representation = TestEntity::represent($arr, ['only' => ['id', 'city', ['user' => ['name']]], 'serializable' => true]);
        $this->assertEquals(['id' => 1, 'city' => 'New York', 'user' => ['name' => 'Jane']], $representation);
    }

    public function testOnlyPreserveNesting()
    {
        // It preserves nesting
        TestEntity::expose('additional', function () {
            TestEntity::expose('something');
        });

        $representation = TestEntity::represent(['something' => 123], ['only' => [['additional' => ['something']]], 'serializable' => true]);
        $this->assertEquals(['additional' => ['something' => 123]], $representation);
    }

    public function testExcept()
    {
        TestEntity::expose('id', 'name', 'phone', 'city');
        $obj = new TestAbGet(['id' => 1, 'name' => 'John', 'phone' => '5551234514', 'city' => 'New York']);

        $representation = TestEntity::represent($obj, ['except' => ['phone', 'city'], 'serializable' => true]);
        $this->assertEquals(is_array($representation), true);
        $this->assertEquals(['id' => 1, 'name' => 'John'], $representation);

        $representation = TestEntity::represent($obj, ['except' => 'phone', 'serializable' => true]);
        $this->assertEquals(['id' => 1, 'name' => 'John', 'city' => 'New York'], $representation);
    }

    public function testExceptNestedExposure()
    {
        // It can specify children attributes with `except` option
        TestEntity::expose('id', 'name', 'phone', 'city');
        TestEntity::expose('user', ['using' => UserEntity::class]);
        $arr = ['id' => 1, 'name' => 'John', 'phone' => '5551234514', 'city' => 'New York', 'user' => ['id' => 2, 'name' => 'Jane']];

        $representation = TestEntity::represent($arr, ['except' => ['id', 'city', ['user' => ['name']]], 'serializable' => true]);
        $this->assertEquals(['name' => 'John', 'phone' => '5551234514', 'user' => ['id' => 2]], $representation);
    }

    public function testOnlyAndExcept()
    {
        // It returns only fields specified in the only option and not specified in the `except` option
        TestEntity::expose('id', 'name', 'phone', 'city');
        $obj = new TestAbGet(['id' => 1, 'name' => 'John', 'phone' => '5551234514', 'city' => 'New York']);

        $representation = TestEntity::represent($obj, ['only' => ['name', 'phone'], 'except' => ['phone'], 'serializable' => true]);
        $this->assertEquals(['name' => 'John'], $representation);
    }

    public function testOnlyAndExceptNestedExposure()
    {
        // It can specify children attributes with mixed `only` and `except` options
        TestEntity::expose('id', 'name', 'phone', 'city');
        TestEntity::expose('user', ['using' => UserEntity::class]);
        $arr = ['id' => 1, 'name' => 'John', 'phone' => '5551234514', 'city' => 'New York', 'user' => ['id' => 2, 'name' => 'Jane']];

        $representation = TestEntity::represent($arr, [
            'only' => ['id', 'name', 'phone', ['user' => ['id', 'name']]],
            'except' => [
                'phone', ['user' => ['id']]
            ], 'serializable' => true
        ]);
        $this->assertEquals(['id' => 1, 'name' => 'John', 'user' => ['name' => 'Jane']], $representation);
    }

    public function testOnlyWithCondition()
    {
        // It returns only specified fields
        TestEntity::expose('id', 'phone');
        TestEntity::withOptions(['if' => ['condition' => true]], function () {
            TestEntity::expose('name', 'mobilePhone');
        });

        $representation = TestEntity::represent(['id' => 1, 'name' => 'John', 'phone' => '4444444444', 'mobilePhone' => '5555555555'], [
            'condition' => true,
            'only' => ['id', 'name'],
            'serializable' => true
        ]);
        $this->assertEquals(['id' => 1, 'name' => 'John'], $representation);
    }

    public function testExceptWithCondition()
    {
        // It does not return fields specified in the `except` option
        TestEntity::expose('id', 'phone');
        TestEntity::withOptions(['if' => ['condition' => true]], function () {
            TestEntity::expose('name', 'mobilePhone');
        });

        $representation = TestEntity::represent(['id' => 1, 'name' => 'John', 'phone' => '4444444444', 'mobilePhone' => '5555555555'], [
            'condition' => true,
            'except' => ['phone', 'mobilePhone'],
            'serializable' => true
        ]);
        $this->assertEquals(['id' => 1, 'name' => 'John'], $representation);
    }

    public function testExposureDependingOnCondition()
    {
        // It choses proper exposure according to condition
        $strategy1 = function ($_) {
            return 'foo';
        };
        $strategy2 = function ($_) {
            return 'bar';
        };

        TestEntity::expose('id', $strategy1);
        TestEntity::expose('id', $strategy2);
        $this->assertEquals(['id' => 'bar'], TestEntity::represent([], ['condition' => false, 'serializable' => true]));
        $this->assertEquals(['id' => 'bar'], TestEntity::represent([], ['condition' => true, 'serializable' => true]));

        TestEntity::unexposeAll();

        TestEntity::expose('id', ['if' => 'condition'], $strategy1);
        TestEntity::expose('id', $strategy2);
        $this->assertEquals(['id' => 'bar'], TestEntity::represent([], ['condition' => false, 'serializable' => true]));
        $this->assertEquals(['id' => 'bar'], TestEntity::represent([], ['condition' => true, 'serializable' => true]));

        TestEntity::unexposeAll();

        TestEntity::expose('id', $strategy1);
        TestEntity::expose('id', ['if' => 'condition'], $strategy2);
        $this->assertEquals(['id' => 'foo'], TestEntity::represent([], ['condition' => false, 'serializable' => true]));
        $this->assertEquals(['id' => 'bar'], TestEntity::represent([], ['condition' => true, 'serializable' => true]));

        TestEntity::unexposeAll();

        TestEntity::expose('id', ['if' => 'condition1'], $strategy1);
        TestEntity::expose('id', ['if' => 'condition2'], $strategy2);
        $this->assertEquals([], TestEntity::represent([], ['condition1' => false, 'condition2' => false, 'serializable' => true]));
        $this->assertEquals(['id' => 'bar'], TestEntity::represent([], ['condition1' => false, 'condition2' => true, 'serializable' => true]));
        $this->assertEquals(['id' => 'foo'], TestEntity::represent([], ['condition1' => true, 'condition2' => false, 'serializable' => true]));
        $this->assertEquals(['id' => 'bar'], TestEntity::represent([], ['condition1' => true, 'condition2' => true, 'serializable' => true]));
    }

    public function testNestedExposuresAndAssocArray()
    {
        // It does not merge nested exposures with plain assoc arrays
        TestEntity::expose('id');
        TestEntity::expose('info', ['if' => 'condition1'], function () {
            TestEntity::expose('a', 'b');
            TestEntity::expose('additional', ['if' => 'condition2'], function ($_) {
                return ['x' => 11, 'y' => 22, 'c' => 123];
            });
        });
        TestEntity::expose('info', ['if' => 'condition2'], function () {
            TestEntity::expose('additional', function () {
                TestEntity::expose('c');
            });
        });
        TestEntity::expose('d', ['as' => 'info', 'if' => 'condition3']);

        $obj = ['id' => 123, 'a' => 1, 'b' => 2, 'c' => 3, 'd' => 4];
        $this->assertEquals(['id' => 123], TestEntity::represent($obj, ['serializable' => true]));
        $this->assertEquals(['id' => 123, 'info' => ['a' => 1, 'b' => 2]], TestEntity::represent($obj, ['condition1' => true, 'serializable' => true]));
        $this->assertEquals(['id' => 123, 'info' => ['additional' => ['c' => 3]]], TestEntity::represent($obj, ['condition2' => true, 'serializable' => true]));
        $this->assertEquals(['id' => 123, 'info' => ['a' => 1, 'b' => 2, 'additional' => ['c' => 3]]], TestEntity::represent($obj, ['condition1' => true, 'condition2' => true, 'serializable' => true]));
        $this->assertEquals(['id' => 123, 'info' => 4], TestEntity::represent($obj, ['condition3' => true, 'serializable' => true]));
        $this->assertEquals(['id' => 123, 'info' => 4], TestEntity::represent($obj, ['condition1' => true, 'condition2' => true, 'condition3' => true, 'serializable' => true]));
    }

    public function testOnlyAlias()
    {
        // It returns only specified fields
        TestEntity::expose('id');
        TestEntity::expose('name', ['as' => 'title']);

        $representation = TestEntity::represent(['id' => 1, 'name' => 'John'], ['condition' => true, 'only' => ['id', 'title'], 'serializable' => true]);
        $this->assertEquals(['id' => 1, 'title' => 'John'], $representation);
    }

    public function testExceptAlias()
    {
        // It does not return fields specified in the `except` option
        TestEntity::expose('id');
        TestEntity::expose('name', ['as' => 'title']);
        TestEntity::expose('phone', ['as' => 'phoneNumber']);

        $representation = TestEntity::represent(['id' => 1, 'name' => 'John', 'phone' => '5555555555'], ['condition' => true, 'except' => ['phoneNumber'], 'serializable' => true]);
        $this->assertEquals(['id' => 1, 'title' => 'John'], $representation);
    }

    public function testEntityAttribute()
    {
        // It returns correctly the children entity attributes
        TestEntity::expose('id', 'name', 'phone');
        TestEntity::expose('user', ['using' => UserEntity::class]);
        TestEntity::expose('admin', ['using' => AdminEntity::class]);

        $obj = ['id' => 1, 'name' => 'John', 'phone' => '5555555555', 'user' => ['id' => 2, 'name' => 'Jane'], 'admin' => ['id' => 3, 'name' => 'Jack']];
        $representation = TestEntity::represent($obj, ['only' => ['id', 'name', 'user'], 'except' => ['admin'], 'serializable' => true]);
        $this->assertEquals(['id' => 1, 'name' => 'John', 'user' => ['id' => 2, 'name' => 'Jane']], $representation);
    }

    public function testPresentCollectionAccessibility()
    {
        // It make the objects accessible
        TestEntity::presentCollection(true);
        TestEntity::expose('items');

        $representation = TestEntity::represent(array_fill(0, 4, new TestAbGet([])));
        $this->assertEquals(TestEntity::class, get_class($representation));
        $this->assertEquals(is_array($representation->object), true);
        $this->assertEquals(array_key_exists('items', $representation->object), true);
        $this->assertEquals(is_array($representation->object['items']), true);
        $this->assertEquals(count($representation->object['items']), 4);
    }

    public function testPresentCollectionRootName()
    {
        // It serializes items with my root name
        TestEntity::presentCollection(true, 'myItems');
        TestEntity::expose('myItems');

        $representation = TestEntity::represent(array_fill(0, 4, new TestAbGet([])), ['serializable' => true]);
        $this->assertEquals(is_array($representation), true);
        $this->assertEquals(array_key_exists('myItems', $representation), true);
        $this->assertEquals(is_array($representation['myItems']), true);
        $this->assertEquals(count($representation['myItems']), 4);
    }

    public function testRootKeysSingleObject()
    {
        // It allows a root element name to be specified
        TestEntity::root('things', 'thing');

        $representation = TestEntity::represent(new TestAbGet([]));
        $this->assertEquals(is_array($representation), true);
        $this->assertEquals(array_key_exists('thing', $representation), true);
        $this->assertEquals(TestEntity::class, get_class($representation['thing']));
    }

    public function testRootKeysArrayOfObjects()
    {
        // It allows a root element name to be specified
        TestEntity::root('things', 'thing');

        $representation = TestEntity::represent(array_fill(0, 4, new TestAbGet([])));

        $this->assertEquals(is_array($representation), true);
        $this->assertEquals(array_key_exists('things', $representation), true);
        $this->assertEquals(is_array($representation['things']), true);
        $this->assertEquals(count($representation['things']), 4);
        $this->assertEquals(array_fill(0, 4, TestEntity::class), array_map('get_class', $representation['things']));
    }

    public function testRootKeysOverride()
    {
        // It can be disabled
        TestEntity::root('things', 'thing');

        $representation = TestEntity::represent(array_fill(0, 4, new TestAbGet([])), ['root' => false]);

        $this->assertEquals(is_array($representation), true);
        $this->assertEquals(count($representation), 4);
        $this->assertEquals(array_fill(0, 4, TestEntity::class), array_map('get_class', $representation));
    }

    public function testRootKeysOverrideName()
    {
        // It can use a different name
        TestEntity::root('things', 'thing');

        $representation = TestEntity::represent(array_fill(0, 4, new TestAbGet([])), ['root' => 'others']);

        $this->assertEquals(is_array($representation), true);
        $this->assertEquals(array_key_exists('others', $representation), true);
        $this->assertEquals(is_array($representation['others']), true);
        $this->assertEquals(count($representation['others']), 4);
        $this->assertEquals(array_fill(0, 4, TestEntity::class), array_map('get_class', $representation['others']));
    }

    public function testSingularRootKeySingleObject()
    {
        // It allows a root element name to be specified
        TestEntity::root(null, 'thing');

        $representation = TestEntity::represent(new TestAbGet([]));
        $this->assertEquals(is_array($representation), true);
        $this->assertEquals(array_key_exists('thing', $representation), true);
        $this->assertEquals(TestEntity::class, get_class($representation['thing']));
    }

    public function testSingularRootKeyArrayOfObjects()
    {
        // It allows a root element name to be specified
        TestEntity::root(null, 'thing');

        $representation = TestEntity::represent(array_fill(0, 4, new TestAbGet([])));
        $this->assertEquals(is_array($representation), true);
        $this->assertEquals(count($representation), 4);
        $this->assertEquals(array_fill(0, 4, TestEntity::class), array_map('get_class', $representation));
    }

    public function testPluralRootKeySingleObject()
    {
        // It allows a root element name to be specified
        TestEntity::root('things');

        $representation = TestEntity::represent(new TestAbGet([]));
        $this->assertEquals(TestEntity::class, get_class($representation));
    }

    public function testPluralRootKeyArrayOfObjects()
    {
        // It allows a root element name to be specified
        TestEntity::root('things');

        $representation = TestEntity::represent(array_fill(0, 4, new TestAbGet([])));
        $this->assertEquals(is_array($representation), true);
        $this->assertEquals(array_key_exists('things', $representation), true);
        $this->assertEquals(is_array($representation['things']), true);
        $this->assertEquals(count($representation['things']), 4);
        $this->assertEquals(array_fill(0, 4, TestEntity::class), array_map('get_class', $representation['things']));
    }

    public function testNullObj()
    {
        // It does not blow up when the model is null
        TestEntity::expose('name');

        $this->assertEquals(null, (new TestEntity(null))->serializableArray());
    }

    public function testExposeEntityPrivateOrProtecteProperty()
    {
        // exposes values of private method calls
        $someClass = new class([]) extends Entity
        {
            use EntityTrait;
            private static function initialize()
            {
                self::expose('id', 'name');
            }
            protected $id = 1;
            private $name = 'John';
        };

        $representation = $someClass::represent([]);
        $this->assertEquals(['id' => 1, 'name' => 'John'], $representation->serializableArray());

        Reflection::resetCache();
        Reflection::$disableProtectedProps = true;
        $representation = $someClass::represent(['id' => 2]);
        $this->assertEquals(['id' => 2, 'name' => 'John'], $representation->serializableArray());

        Reflection::resetCache();
        Reflection::$disablePrivateProps = true;
        $representation = $someClass::represent(['id' => 2, 'name' => 'Jack']);
        $this->assertEquals(['id' => 2, 'name' => 'Jack'], $representation->serializableArray());
    }

    public function testExposeEntityPrivateOrProtectedMethod()
    {
        // exposes values of private method calls
        $someClass = new class([]) extends Entity
        {
            use EntityTrait;
            private static function initialize()
            {
                self::expose('id', 'name');
            }
            protected function id()
            {
                return 1;
            }
            private function name()
            {
                return 'John';
            }
        };

        $representation = $someClass::represent([]);
        $this->assertEquals(['id' => 1, 'name' => 'John'], $representation->serializableArray());

        Reflection::resetCache();
        Reflection::$disableProtectedMethods = true;
        $representation = $someClass::represent(['id' => 2]);
        $this->assertEquals(['id' => 2, 'name' => 'John'], $representation->serializableArray());

        Reflection::resetCache();
        Reflection::$disablePrivateMethods = true;
        $representation = $someClass::represent(['id' => 2, 'name' => 'Jack']);
        $this->assertEquals(['id' => 2, 'name' => 'Jack'], $representation->serializableArray());
    }

    public function testExposeObjectPrivateOrProtectedProperty()
    {
        // exposes values of protected/private properties
        $someClass = new class
        {
            protected $id = 1;
            private $name = 'John';
        };
        $klass = new $someClass();

        TestEntity::expose('id', 'name');

        $representation = TestEntity::represent($klass);
        $this->assertEquals(['id' => 1, 'name' => 'John'], $representation->serializableArray());

        Reflection::resetCache();
        Reflection::$disableProtectedProps = true;
        $this->expectException(MissingPropertyException::class);
        (TestEntity::represent($klass))->serializableArray();

        Reflection::resetCache();
        Reflection::$disableProtectedProps = false;
        Reflection::$disablePrivateProps = true;
        $this->expectException(MissingPropertyException::class);
        (TestEntity::represent($klass))->serializableArray();
    }

    public function testExposeObjectPrivateOrProtectedMethod()
    {
        // exposes values of protected/private method calls
        $someClass = new class
        {
            protected function id()
            {
                return 1;
            }
            private function name()
            {
                return 'John';
            }
        };
        $klass = new $someClass();

        TestEntity::expose('id', 'name');

        $representation = TestEntity::represent($klass);
        $this->assertEquals(['id' => 1, 'name' => 'John'], $representation->serializableArray());

        Reflection::resetCache();
        Reflection::$disableProtectedMethods = true;
        $this->expectException(MissingPropertyException::class);
        (TestEntity::represent($klass))->serializableArray();

        Reflection::resetCache();
        Reflection::$disableProtectedMethods = false;
        Reflection::$disablePrivateMethods = true;
        $this->expectException(MissingPropertyException::class);
        (TestEntity::represent($klass))->serializableArray();
    }

    public function testSafe()
    {
        // It does not throw an exception when an attribute is not found on the object
        TestEntity::expose('name', 'nonExistentProperty', ['safe' => true]);

        $representation = TestEntity::represent(new TestAbGet(['name' => 'John']));
        $this->assertEquals(['name' => 'John', 'nonExistentProperty' => null], $representation->serializableArray());
    }

    public function testSafePrivateMethod()
    {
        // exposes values of private method calls
        $someClass = new class
        {
            private $id = 1;

            private function name()
            {
                return 'John';
            }
        };
        $klass = new $someClass();

        TestEntity::expose('id', 'name', ['safe' => true]);

        $representation = TestEntity::represent($klass);
        $this->assertEquals(['id' => 1, 'name' => 'John'], $representation->serializableArray());
    }

    public function testSafeOnNonExistentProperty()
    {
        // It does not throw an exception when a property is not found on the object
        TestEntity::expose('email', 'nonExistentProperty', 'name', ['safe' => true]);

        $representation = TestEntity::represent(['name' => 'John', 'email' => 'john@ab.net']);
        $this->assertEquals(['email', 'nonExistentProperty', 'name'], array_keys($representation->serializableArray()));
        $this->assertEquals(null, $representation->serializableArray()['nonExistentProperty']);
    }

    public function testSafeOnNonExistentPropertyAndCriteria()
    {
        // It does expose properties that don't exist on the object as null if criteria is true
        TestEntity::expose('name');
        TestEntity::expose('nonExistentProperty', ['safe' => true, 'if' => function () {
            return false;
        }]);
        TestEntity::expose('nonExistentProperty2', ['safe' => true, 'if' => function () {
            return true;
        }]);

        $representation = TestEntity::represent(['name' => 'John']);
        $this->assertEquals(['name', 'nonExistentProperty2'], array_keys($representation->serializableArray()));
    }

    public function testEmbeddedObjectRespondingToSerializableArray()
    {

        $EmbeddedExampleWithOne = new class
        {
            public function name()
            {
                return 'abc';
            }
            public function embedded()
            {
                return new EmbeddedExample();
            }
        };
        TestEntity::expose('name', 'embedded');

        $representation = TestEntity::represent(new $EmbeddedExampleWithOne());
        $this->assertEquals(['name' => 'abc', 'embedded' => ['abc' => 'def']], $representation->serializableArray());
    }

    public function testEmbeddedObjectsRespondingToSerializableArray()
    {

        $EmbeddedExampleWithMany = new class
        {
            public $name = 'abc';
            public function embedded()
            {
                return [new EmbeddedExample(), new EmbeddedExample()];
            }
        };
        TestEntity::expose('name', 'embedded');

        $representation = TestEntity::represent(new $EmbeddedExampleWithMany());
        $this->assertEquals(['name' => 'abc', 'embedded' => [['abc' => 'def'], ['abc' => 'def']]], $representation->serializableArray());
    }

    public function testEmbeddedArrayRespondingToSerializableArray()
    {

        $EmbeddedExampleWithArray = new class
        {
            public $name = 'abc';
            public function embedded()
            {
                return ['a' => null, 'b' => new EmbeddedExample()];
            }
        };
        TestEntity::expose('name', 'embedded');

        $representation = TestEntity::represent(new $EmbeddedExampleWithArray());
        $this->assertEquals(['name' => 'abc', 'embedded' => ['a' => null, 'b' => ['abc' => 'def']]], $representation->serializableArray());
    }

    public function testAttrPath()
    {
        // For all kinds of attributes
        TestEntity::expose('id', ['func' => 'PhpGrape\Tests\path']);
        TestEntity::expose('foo', ['as' => 'bar', 'func' => 'PhpGrape\Tests\path']);
        TestEntity::expose('title', function () {
            TestEntity::expose('full', function () {
                TestEntity::expose('prefix', ['as' => 'pref', 'func' => 'PhpGrape\Tests\path']);
                TestEntity::expose('main', ['func' => 'PhpGrape\Tests\path']);
            });
        });
        TestEntity::expose('friends', ['as' => 'social', 'using' => PathUserEntity::class]);
        TestEntity::expose('extra', ['using' => PathExtraEntity::class]);
        TestEntity::expose('nested', ['using' => PathNestedEntity::class]);

        $obj = [
            'name' => 'Bob Bobson',
            'email' => 'bob@example.com',
            'friends' => [
                ['name' => 'Friend 1', 'email' => 'friend1@example.com', 'characteristics' => [], 'fantasies' => [], 'friends' => []],
                ['name' => 'Friend 2', 'email' => 'friend2@example.com', 'characteristics' => [], 'fantasies' => [], 'friends' => []]
            ],
            'extra' => ['key' => 'foo', 'value' => 'bar'],
            'nested' => [
                ['name' => 'n1', 'data' => ['key' => 'ex1', 'value' => 'v1']],
                ['name' => 'n2', 'data' => ['key' => 'ex2', 'value' => 'v2']]
            ]
        ];

        $this->assertEquals([
            'id' => 'id',
            'bar' => 'bar',
            'title' => ['full' => ['pref' => 'title/full/pref', 'main' => 'title/full/main']],
            'social' => [
                ['full_name' => 'social/full_name', 'email' => ['addr' => 'social/email/addr']],
                ['full_name' => 'social/full_name', 'email' => ['addr' => 'social/email/addr']]
            ],
            'extra' => ['key' => 'extra/key', 'value' => 'extra/value'],
            'nested' => [
                ['name' => 'nested/name', 'data' => ['key' => 'nested/data/key', 'value' => 'nested/data/value']],
                ['name' => 'nested/name', 'data' => ['key' => 'nested/data/key', 'value' => 'nested/data/value']]
            ]
        ], (new TestEntity($obj))->serializableArray());
    }

    public function testAttrPathProp()
    {
        // It allows customize path of a prop
        TestEntity::expose('characteristics', ['using' => PathExtraEntity::class, 'attr_path' => function ($_, $o) {
            return 'character';
        }]);
        TestEntity::expose('characteristics', ['as' => 'char', 'using' => PathExtraEntity::class, 'attr_path' => 'character']);

        $obj = ['characteristics' => [['key' => 'hair_color', 'value' => 'brown']]];

        $this->assertEquals([
            'characteristics' => [['key' => 'character/key', 'value' => 'character/value']],
            'char' => [['key' => 'character/key', 'value' => 'character/value']],
        ], (new TestEntity($obj))->serializableArray());
    }

    public function testAttrPathNull()
    {
        // It can drop one nest level by set path_for to null
        TestEntity::expose('characteristics', ['using' => PathExtraEntity::class, 'attr_path' => function ($_, $o) {
            return null;
        }]);
        TestEntity::expose('characteristics', ['as' => 'char', 'using' => PathExtraEntity::class, 'attr_path' => null]);

        $obj = ['characteristics' => [['key' => 'hair_color', 'value' => 'brown']]];

        $this->assertEquals([
            'characteristics' => [['key' => 'key', 'value' => 'value']],
            'char' => [['key' => 'key', 'value' => 'value']],
        ], (new TestEntity($obj))->serializableArray());
    }

    public function testChildRepresentationDisableRoot()
    {
        // It disables root key name for child representations
        $FriendEntity = new class([]) extends Entity
        {
            use EntityTrait;
            private static function initialize()
            {
                self::root('friends', 'friend');
                self::expose('name', 'email');
            }
        };

        TestEntity::expose('friends', ['using' => $FriendEntity]);

        $obj = [
            'friends' => [
                ['name' => 'John', 'email' => 'john@ab.net'],
                ['name' => 'Jane', 'email' => 'jane@ab.net'],
            ],
        ];
        $representation = (TestEntity::represent($obj))->serializableArray();

        $this->assertEquals([
            'friends' => [
                ['name' => 'John', 'email' => 'john@ab.net'],
                ['name' => 'Jane', 'email' => 'jane@ab.net'],
            ],
        ], $representation);
    }

    public function testChildRepresentationFuncReturnsMultipleObjects()
    {
        // It passes through the func which returns an array of objects with custom options['using']
        $FriendEntity = new class([]) extends Entity
        {
            use EntityTrait;
            private static function initialize()
            {
                self::root('friends', 'friend');
                self::expose('name', 'email');
            }
        };
        TestEntity::expose('customFriends', ['using' => $FriendEntity], function ($user) {
            return $user['friends'];
        });

        $obj = [
            'friends' => [
                ['name' => 'John', 'email' => 'john@ab.net'],
                ['name' => 'Jane', 'email' => 'jane@ab.net'],
            ],
        ];
        $representation = TestEntity::represent($obj);

        $this->assertEquals([
            'customFriends' => [
                ['name' => 'John', 'email' => 'john@ab.net'],
                ['name' => 'Jane', 'email' => 'jane@ab.net'],
            ],
        ], $representation->serializableArray());
    }

    public function testChildRepresentationFuncReturnsSingleObject()
    {
        // It passes through the proc which returns single object with custom options['using']
        $FriendEntity = new class([]) extends Entity
        {
            use EntityTrait;
            private static function initialize()
            {
                self::root('friends', 'friend');
                self::expose('name', 'email');
            }
        };
        TestEntity::expose('firstFriend', ['using' => $FriendEntity], function ($user) {
            return $user['friends'][0];
        });

        $obj = [
            'friends' => [
                ['name' => 'John', 'email' => 'john@ab.net'],
                ['name' => 'Jane', 'email' => 'jane@ab.net'],
            ],
        ];
        $representation = TestEntity::represent($obj);

        $this->assertEquals(['firstFriend' => ['name' => 'John', 'email' => 'john@ab.net']], $representation->serializableArray());
    }

    public function testChildrenRepresentationFuncReturnsNull()
    {
        // It passes through the proc which returns empty with custom options['using']
        $FriendEntity = new class([]) extends Entity
        {
            use EntityTrait;
            private static function initialize()
            {
                self::root('friends', 'friend');
                self::expose('name', 'email');
            }
        };
        TestEntity::expose('firstFriend', ['using' => $FriendEntity], function ($user) {
            return null;
        });

        $obj = [
            'friends' => [
                ['name' => 'John', 'email' => 'john@ab.net'],
                ['name' => 'Jane', 'email' => 'jane@ab.net'],
            ],
        ];
        $representation = TestEntity::represent($obj);

        $this->assertEquals(['firstFriend' => null], $representation->serializableArray());
    }

    public function testChildRepresentationWithOptions()
    {
        // It passes through custom options
        $FriendEntity = new class([]) extends Entity
        {
            use EntityTrait;
            private static function initialize()
            {
                self::root('friends', 'friend');
                self::expose('name');
                self::expose('email', ['if' => ['userType' => 'admin']]);
            }
        };
        TestEntity::expose('friends', ['using' => $FriendEntity]);

        $obj = [
            'friends' => [
                ['name' => 'John', 'email' => 'john@ab.net'],
                ['name' => 'Jane', 'email' => 'jane@ab.net'],
            ],
        ];
        $representation = TestEntity::represent($obj);
        $this->assertEquals(['friends' => [['name' => 'John'], ['name' => 'Jane']]], $representation->serializableArray());

        $representation = TestEntity::represent($obj, ['userType' => 'admin']);
        $this->assertEquals('john@ab.net', $representation->serializableArray()['friends'][0]['email']);
        $this->assertEquals('jane@ab.net', $representation->serializableArray()['friends'][1]['email']);
    }

    public function testDocumentationEmpty()
    {
        // It returns an empty array if no documentation is provided
        TestEntity::expose('name');
        $this->assertEquals([], TestEntity::documentation());
    }

    public function testDocumentationArray()
    {
        // It returns each defined documentation array
        $doc = ['type' => 'foo', 'desc' => 'bar'];
        TestEntity::expose('name', ['documentation' => $doc]);
        TestEntity::expose('email', ['documentation' => $doc]);
        TestEntity::expose('birthday');

        $this->assertEquals(['name' => $doc, 'email' => $doc], TestEntity::documentation());
    }

    public function testDocumentationAndAliasPropName()
    {
        // It returns each defined documentation array with `as` param considering
        $doc = ['type' => 'foo', 'desc' => 'bar'];
        TestEntity::expose('name', ['documentation' => $doc, 'as' => 'label']);
        TestEntity::expose('email', ['documentation' => $doc]);
        TestEntity::expose('birthday');

        $this->assertEquals(['label' => $doc, 'email' => $doc], TestEntity::documentation());
    }

    public function testDocumentationAndAliasFunc()
    {
        // It throw an exception if `as` is a function
        $doc = ['type' => 'foo', 'desc' => 'bar'];
        TestEntity::expose('name', ['documentation' => $doc, 'as' => function ($_) {
        }]);
        $this->expectException(InvalidTypeException::class);
        TestEntity::documentation();
    }

    public function testDocumentationMemoization()
    {
        // It resets memoization when exposing additional properties
        TestEntity::expose('x', ['documentation' => ['desc' => 'just x']]);

        $rc = new \ReflectionClass(TestEntity::class);
        $prop = $rc->getProperty('documentation');
        $prop->setAccessible(true);
        $this->assertEquals($prop->getValue(), null);

        TestEntity::documentation();
        $this->assertEquals(is_array($prop->getValue()), true);

        TestEntity::expose('y', ['documentation' => ['desc' => 'just y']]);
        $this->assertEquals($prop->getValue(), null);

        $this->assertEquals(['x' => ['desc' => 'just x'], 'y' => ['desc' => 'just y']], TestEntity::documentation());
    }

    public function testDocumentationOnlyRootExposures()
    {
        // It includes only root exposures
        TestEntity::expose('name', ['documentation' => ['desc' => 'foo']]);
        TestEntity::expose('nesting', function () {
            TestEntity::expose('smth', ['documentation' => ['desc' => 'should not be seen']]);
        });
        $this->assertEquals(['name' => ['desc' => 'foo']], TestEntity::documentation());
    }

    public function testKeyTransformer()
    {
        Entity::transformKeys('camel');

        $entityClass = new class([]) extends Entity
        {
            use EntityTrait;
            private static function initialize()
            {
                self::expose('foo_bar');
                self::expose('baz', ['as' => 'baz-baz-baz']);
            }
        };

        TestEntity::root(null, 'simple_root');
        TestEntity::expose('abc_def_ähi', ['documentation' => ['desc' => 'foo']]);
        TestEntity::expose('foo', ['as' => 'bar_baz-foo']);
        TestEntity::expose('contact_info', ['documentation' => ['desc' => 'info']], function () {
            TestEntity::expose('info', ['as' => 'full-info'], function () {
                TestEntity::expose('name', ['as' => function () {
                    return 'first_name-and-last_name';
                }]);
                TestEntity::expose('email_and_phone');
            });
        });
        TestEntity::expose('friends', ['as' => 'social_friends', 'using' => $entityClass]);
        TestEntity::expose('extra', ['as' => 'extra_users', 'merge' => true, 'using' => $entityClass]);

        $obj = [
            'abc_def_ähi' => 1,
            'foo' => 2,
            'name' => 3,
            'email_and_phone' => 4,
            'friends' => [
                ['foo_bar' => 5, 'baz' => 6],
                ['foo_bar' => 7, 'baz' => 8],
            ],
            'extra' => ['foo_bar' => 9, 'baz' => 10],
        ];

        $this->assertEquals([
            'simpleRoot' => [
                'abcDefÄhi' => 1,
                'barBazFoo' => 2,
                'contactInfo' => [
                    'fullInfo' => [
                        'firstNameAndLastName' => 3,
                        'emailAndPhone' => 4,
                    ]
                ],
                'socialFriends' => [
                    ['fooBar' => 5, 'bazBazBaz' => 6],
                    ['fooBar' => 7, 'bazBazBaz' => 8],
                ],
                'fooBar' => 9,
                'bazBazBaz' => 10,
            ]
        ], TestEntity::represent($obj, ['serializable' => true]));
        $this->assertEquals(['abcDefÄhi', 'contactInfo'], array_keys(TestEntity::documentation()));
    }

    public function testKeyTransformerCustom()
    {
        Entity::transformKeys(function ($key) {
            return 'get_' . $key;
        });
        TestEntity::expose('name');

        $representation = TestEntity::represent(['name' => 'John']);
        $this->assertEquals(['get_name' => 'John'], $representation->serializableArray());
    }

    public function testAdapter()
    {
        Entity::setPropValueAdapter('test', [
            'condition' => function () {
                $model = 'TestAbGet';
                return (new \ReflectionClass($this->object))->getShortName() === $model;
            },
            'getPropValue' => function ($prop, $safe, $cache) {
                if ($prop === 'id') return 'id';

                $class = get_class($this->object);
                $value = $cache->get($class, $prop);
                if ($value !== null) return $value;

                $cache->set($class, $prop, $this->object->{$prop});
                return $this->object->{$prop};
            }
        ]);

        $representation = UserEntity::represent(new TestAbGet(['id' => 1, 'name' => 'John']));
        $this->assertEquals(['id' => 'id', 'name' => 'John'], $representation->serializableArray());

        // Cache test
        $representation = UserEntity::represent(new TestAbGet(['id' => 2, 'name' => 'Jane']));
        $this->assertEquals(['id' => 'id', 'name' => 'John'], $representation->serializableArray());

        Entity::setPropValueAdapter('test', null);
    }
}
