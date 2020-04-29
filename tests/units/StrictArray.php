<?php

namespace Asinius\tests\units;

use atoum;

class StrictArray extends atoum\test
{

    private $_inputs = [];


    public function __construct ()
    {
        $this->_inputs = [
            'empty_array'   => [],
            'simple_array'  => ['a', 'b', 'c', 'd'],
            'simple_hash'   => ['a' => 0, 'b' => 1, 'c' => 2, 'd' => 3],
            'mixed_hash'    => [0 => 'a', 'b' => 'c', 5 => 'd'],
            'not_array'     => new \LogicException(),
            'merge_arrays'  => [
                ['a', 'b', 'c', 'd', 'e', 'f'],
                ['g' => 0, 'h' => 1, 'i' => 2, 'j' => 3, 'k' => 4, 'l' => 5],
                ['m', 'n', 'o', 'p', 'q', 'r', 's', 't'],
                [0 => 'z', 1 => 'y', 2 => 'x', 3 => 'w', 4 => 'v', 5 => 'u'],
            ],
            'strings'       => [
                'a'     => 'Lorem',
                'b'     => 'ipsum',
                'c'     => 'dolor',
                'd'     => 'sit',
                'e'     => 'amet',
                'f'     => 'consectetur',
                'g'     => 'adipiscing',
                'h'     => 'elit',
                'i'     => 'sed',
                'j'     => 'do',
                'k'     => 'eiusmod',
                'l'     => 'tempor',
                'm'     => 'incididunt',
                'n'     => 'ut',
                'o'     => 'labore',
                'p'     => 'et',
                'q'     => 'dolore',
                'r'     => 'magna',
                's'     => 'aliqua',
                't'     => 'Ut',
                'u'     => 'enim',
                'v'     => 'ad',
                'w'     => 'minim',
                'x'     => 'veniam',
                'y'     => 'quis',
                'z'     => 'nostrud',
                'aa'    => 'exercitation',
                'ab'    => 'ullamco',
                'ac'    => 'laboris',
                'ad'    => 'nisi',
                'ae'    => 'ut',
                'af'    => 'aliquip',
                'ag'    => 'ex',
                'ah'    => 'ea',
                'ai'    => 'commodo',
                'aj'    => 'consequat',
                'ak'    => 'Duis',
                'al'    => 'aute',
                'am'    => 'irure',
                'an'    => 'dolor',
                'ao'    => 'in',
                'ap'    => 'reprehenderit',
                'aq'    => 'in',
                'ar'    => 'voluptate',
                'as'    => 'velit',
                'at'    => 'esse',
                'au'    => 'cillum',
                'av'    => 'dolore',
                'aw'    => 'eu',
                'ax'    => 'fugiat',
                'ay'    => 'nulla',
                'az'    => 'pariatur',
                'ba'    => 'Excepteur',
                'bb'    => 'sint',
                'bc'    => 'occaecat',
                'bd'    => 'cupidatat',
                'be'    => 'non',
                'bf'    => 'proident',
                'bg'    => 'sunt',
                'bh'    => 'in',
                'bi'    => 'culpa',
                'bj'    => 'qui',
                'bk'    => 'officia',
                'bl'    => 'deserunt',
                'bm'    => 'mollit',
                'bn'    => 'anim',
                'bo'    => 'id',
                'bp'    => 'est',
                'bq'    => 'laborum',
            ],
        ];
        parent::__construct();
    }


    /**
     * A handful of basic functional tests collected together. This makes
     * debugging failed tests a bit easier in this environment.
     */
    public function testTheBasics ()
    {
        //  Keys and values.
        $test_input = $this->_inputs['simple_hash'];
        $array = new \Asinius\StrictArray($test_input);
        $this->object($array)->isInstanceOf('\Asinius\StrictArray');
        reset($test_input);
        foreach ($array as $key => $value) {
            if ( $key !== key($test_input) ) {
                echo "Expecting key " . key($test_input) . " but got $key instead\n";
            }
            else if ( $value !== current($test_input) ) {
                echo "Expecting value " . current($test_input) . " but got $value instead\n";
            }
            else {
                next($test_input);
                continue;
            }
            var_dump($array);
            die("Failed basic test: simple_hash");
        }
        //  Unset.
        $test_input = $this->_inputs['simple_array'];
        $array = new \Asinius\StrictArray($test_input);
        unset($array[2]);
        unset($test_input[2]);
        if ( $array->count() != count($test_input) || $array->keys() != array_keys($test_input) || $array->values() != array_values($test_input) ) {
            var_dump($array);
            die("Failed basic test: unset simple_array");
        }
    }


    /**
     * Tests for StrictArray::__construct()
     */
    public function testConstructor ()
    {
        $this
            ->given($array = new \Asinius\StrictArray())
            ->then
                ->integer($array->count())->isEqualTo(0);

        $this
            ->given($array = new \Asinius\StrictArray(['a', 'b', 'c', 'd']))
            ->then
                ->string($array[0])->isEqualTo('a')
                ->string($array[1])->isEqualTo('b')
                ->string($array[2])->isEqualTo('c')
                ->string($array[3])->isEqualTo('d')
                ->integer($array->count())->isEqualTo(4);

        $this
            ->given($array = new \Asinius\StrictArray('a', 'b', 'c', 'd'))
            ->then
                ->string($array[0])->isEqualTo('a')
                ->string($array[1])->isEqualTo('b')
                ->string($array[2])->isEqualTo('c')
                ->string($array[3])->isEqualTo('d')
                ->integer($array->count())->isEqualTo(4);

        $this
            ->given($array = new \Asinius\StrictArray(['a' => 0, 'b' => 1, 'c' => 2, 'd' => 3]))
            ->then
                ->integer($array['a'])->isEqualTo(0)
                ->integer($array['b'])->isEqualTo(1)
                ->integer($array['c'])->isEqualTo(2)
                ->integer($array['d'])->isEqualTo(3)
                ->integer($array->count())->isEqualTo(4);

        $this
            ->given($array = new \Asinius\StrictArray(new \ArrayIterator(['a', 'b', 'c', 'd'])))
            ->then
                ->string($array[0])->isEqualTo('a')
                ->string($array[1])->isEqualTo('b')
                ->string($array[2])->isEqualTo('c')
                ->string($array[3])->isEqualTo('d')
                ->integer($array->count())->isEqualTo(4);

        $foo = new \LogicException();
        $this
            ->given($array = new \Asinius\StrictArray($foo))
            ->then
                ->object($array[0])->isIdenticalTo($foo)
                ->integer($array->count())->isEqualTo(1);

    }


    /**
     * Ensure that integer keys and string-numeric ("100") keys are handled
     * correctly, since that's the whole point of this thing.
     */
    public function testNumericStringKeys ()
    {
        $array = new \Asinius\StrictArray();
        $array[100] = 'a';
        $array["100"] = 'b';
        $this
            ->string($array[100])->isEqualTo('a')
            ->string($array["100"])->isEqualTo('b')
            ->integer($array->count())->isEqualTo(2);
        unset($array[100]);
        $array['100'] = 'c';
        $this
            ->integer($array->count())->isEqualTo(1)
            ->string($array["100"])->isEqualTo('c')
            ->variable($array[100])->isNull();
    }


    /**
     * Run a series of tests against the current(), key(), next(), valid(), etc.
     * iteration-related functions.
     */
    public function testForEach ()
    {
        foreach (['simple_array', 'simple_hash'] as $test) {
            $test_input = $this->_inputs[$test];
            $this
                ->given($array = new \Asinius\StrictArray($test_input))
                ->then
                    ->integer($array->count())->isEqualTo(count($test_input));
            reset($test_input);
            foreach ($array as $key => $value) {
                $this
                    ->variable($key)->isIdenticalTo(key($test_input))
                    ->variable($value)->isIdenticalTo(current($test_input))
                    ->variable($array->key())->isIdenticalTo(key($test_input))
                    ->variable($array->current())->isIdenticalTo(current($test_input));
                next($test_input);
            }
            //  Also test post-loop nulls and falses.
            $this
                ->variable($array->key())->isIdenticalTo(key($test_input))
                ->variable($array->current())->isIdenticalTo(current($test_input))
                ->variable($array->valid())->isIdenticalTo(false);
        }
    }


    /**
     * Ensure that an empty array returns false if rewind() is called.
     */
    public function testRewind ()
    {
        $this->given($array = new \Asinius\StrictArray(['foo' => 'bar', 'baz' => 'quux']));
        unset($array['foo']);
        unset($array['baz']);
        $this
            ->integer($array->count())->isEqualTo(0)
            ->boolean($array->rewind())->isEqualTo(false);
    }


    /**
     * Ensure that indexes are incremented and handled correctly for sequential
     * and non-sequential arrays and arrays that start sequential but become
     * non-sequential.
     */
    public function testOffsetSet ()
    {
        //  Create a sequential array:
        $this
            ->given($array = new \Asinius\StrictArray('a', 'b', 'c', 'd'))
            ->integer($array->count())->isEqualTo(4);

        //  Add a new element way off the end of the array...
        $array[100] = 'e';
        //  ...and a new element after that...
        $array[] = 'f';
        //  ...and make sure indexes, counts, and so on are all correct.
        $this
            ->integer($array->count())->isEqualTo(6)
            ->array($array->keys())->isEqualTo([0, 1, 2, 3, 100, 101])
            ->array($array->values())->isEqualTo(['a', 'b', 'c', 'd', 'e', 'f']);
    }


    /**
     * Thoroughly test unset-related functions to ensure that indexes and keys
     * are all juggled appropriately.
     */
    public function testUnset ()
    {
        //  String vs. numeric keys
        $this
            ->given($array = new \Asinius\StrictArray())
            ->and($array["100"] = "one-hundred")
            ->and($array[100] = 100);
        unset($array[100]);
        $this
            ->integer($array->count())->isEqualTo(1)
            ->string($array["100"])->isEqualTo("one-hundred")
            ->variable($array[100])->isNull()
            ->boolean(empty($array[100]))->isTrue();

        //  Unset from the end of a linear array.
        $this
            ->given($array = new \Asinius\StrictArray(['a', 'b', 'c', 'd', 'e', 'f']));
        unset($array[$array->count()-1]);
        $this
            ->integer($array->count())->isEqualTo(5)
            ->array($array->values())->isEqualTo(['a', 'b', 'c', 'd', 'e'])
            ->array($array->keys())->isEqualTo([0, 1, 2, 3, 4]);

        //  Unset from the middle of a linear array.
        unset($array[2]);
        $this
            ->integer($array->count())->isEqualTo(4)
            ->array($array->values())->isEqualTo(['a', 'b', 'd', 'e'])
            ->array($array->keys())->isEqualTo([0, 1, 3, 4]);

        //  Unset a nonexistent key.
        unset($array[6]);
        $this
            ->integer($array->count())->isEqualTo(4)
            ->array($array->values())->isEqualTo(['a', 'b', 'd', 'e'])
            ->array($array->keys())->isEqualTo([0, 1, 3, 4]);

        //  Add another value onto the end of the array.
        $array->append('g');
        $this
            ->integer($array->count())->isEqualTo(5)
            ->array($array->values())->isEqualTo(['a', 'b', 'd', 'e', 'g'])
            ->array($array->keys())->isEqualTo([0, 1, 3, 4, 5]);

        //  Unset the last value again.
        unset($array[5]);
        //  Now that it's no longer a "linear" array, the next index should be 6.
        $array->append('h');
        $this
            ->integer($array->count())->isEqualTo(5)
            ->array($array->values())->isEqualTo(['a', 'b', 'd', 'e', 'h'])
            ->array($array->keys())->isEqualTo([0, 1, 3, 4, 6]);
    }


    /**
     * Simple check of the push() and pop() functions.
     */
    public function testPushAndPop ()
    {
        $array = new \Asinius\StrictArray();
        $this
            ->given($array = new \Asinius\StrictArray())
            ->and(call_user_func_array([$array, 'push'], $this->_inputs['simple_array']))
            ->then
                ->integer($array->count())->isEqualTo(count($this->_inputs['simple_array']));
        //  Now ensure that all elements were pushed in the correct order.
        $i = count($this->_inputs['simple_array']);
        while ( $i-- > 0 ) {
            $this->variable($array->pop())->isIdenticalTo($this->_inputs['simple_array'][$i]);
        }
        //  Count should be 0.
        $this->integer($array->count())->isEqualTo(0);
        //  And null should be returned if pop() is called on an empty array.
        $this->variable($array->pop())->isNull();
    }


    /**
     * Ensure that the StrictArray implements the SeekableIterator functions as
     * suggested in PHP documentation.
     */
    public function testSeekableIterator ()
    {
        $array = new \Asinius\StrictArray($this->_inputs['simple_array']);
        $this->boolean($array instanceof \SeekableIterator)->isTrue();
        $indexes = range(0, count($this->_inputs['simple_array'])-1);
        shuffle($indexes);
        foreach ($indexes as $index) {
            $array->seek($index);
            $this->variable($array->current())->isIdenticalTo($this->_inputs['simple_array'][$index]);
        }
        $array->rewind();
        //  Seeking an invalid position should throw an error.
        $this
            ->exception(function() use ($array) {
                $array->seek(count($this->_inputs['simple_array']));
            })
            ->isInstanceOf('\OutOfBoundsException');
    }


    /**
     * Merge multiple other arrays into one array and ensure that the keys
     * and values match.
     */
    public function testMerge ()
    {
        $array = new \Asinius\StrictArray();
        $merged = call_user_func_array('array_merge', $this->_inputs['merge_arrays']);
        call_user_func_array([$array, 'merge'], $this->_inputs['merge_arrays']);
        $this
            ->given($array)
            ->then
                ->integer($array->count())->isEqualTo(count($merged))
                ->array($array->values())->isEqualTo(array_values($merged))
                ->array($array->keys())->isEqualTo(array_keys($merged));
        //  Merging a non-countable should throw an error.
        $this
            ->exception(function() use ($array) {
                $array->merge(5);
            })
            ->isInstanceOf('\RuntimeException');
    }


    /**
     * Rip through the preg_* functions in the class.
     */
    public function testPregSearch ()
    {
        $pattern = '/[eu]m$/';
        //  Start with string keys.
        $array = new \Asinius\StrictArray($this->_inputs['strings']);
        $strict_matches = $array->preg_search($pattern);
        $php_matches = preg_grep($pattern, $this->_inputs['strings']);
        $this
            ->integer($strict_matches->count())->isEqualTo(count($php_matches))
            ->array($strict_matches->values())->isEqualTo(array_values($php_matches))
            ->array($strict_matches->keys())->isEqualTo(array_keys($php_matches));
        //  Repeat with integer keys.
        $array = new \Asinius\StrictArray(array_values($this->_inputs['strings']));
        $strict_matches = $array->preg_search($pattern);
        $php_matches = preg_grep($pattern, array_values($this->_inputs['strings']));
        $this
            ->integer($strict_matches->count())->isEqualTo(count($php_matches))
            ->array($strict_matches->values())->isEqualTo(array_values($php_matches))
            ->array($strict_matches->keys())->isEqualTo(array_keys($php_matches));
        //  Flip the array and search the keys.
        $array = new \Asinius\StrictArray(array_flip($this->_inputs['strings']));
        $strict_matches = $array->preg_search_keys($pattern);
        $php_matches = array_flip(preg_grep($pattern, $this->_inputs['strings']));
        $this
            ->integer($strict_matches->count())->isEqualTo(count($php_matches))
            ->array($strict_matches->values())->isEqualTo(array_values($php_matches))
            ->array($strict_matches->keys())->isEqualTo(array_keys($php_matches));
    }

}
