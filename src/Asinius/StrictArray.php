<?php

/*******************************************************************************
*                                                                              *
*   Asinius\StrictArray                                                        *
*                                                                              *
*   An Array/ArrayIterator class that strictly compares keys and values,       *
*   solving this problem:                                                      *
*                                                                              *
*       $arr = ["4" => "foo", "5" => "bar", "6" => "baz"];                     *
*       $arr[4] = "oops";                                                      *
*       var_dump($arr);                                                        *
*                                                                              *
*       array(3) {                                                             *
*           [4]=> string(4) "oops"                                             *
*           [5]=> string(3) "bar"                                              *
*           [6]=> string(3) "baz"                                              *
*       }                                                                      *
*                                                                              *
*   and this problem:                                                          *
*                                                                              *
*       $arr1 = ["2016" => "a year", "2017" => "another year"];                *
*       $arr2 = ["2018" => "one more year"];                                   *
*       var_dump(array_merge_recursive($arr1, $arr2));                         *
*                                                                              *
*       array(3) {                                                             *
*           [0]=> string(6) "a year"                                           *
*           [1]=> string(12) "another year"                                    *
*           [2]=> string(13) "one more year"                                   *
*       } //  My keys! Look how they massacred my boy. :-(                     *
*                                                                              *
*   The miserably-badly-named array_replace_recursive() function will at       *
*   least keep the 2016, 2017, and 2018 keys in this example, but it recasts   *
*   them to integers for absolutely no good reason, making them susceptible    *
*   to future confusion.                                                       *
*                                                                              *
*   See for example:                                                           *
*       https://stackoverflow.com/questions/3445953/                           *
*       https://bugs.php.net/bug.php?id=45348                                  *
*       https://stackoverflow.com/questions/4100488/                           *
*                                                                              *
*   The cost for fixing this particular problem is that operations on this     *
*   data structure are far, far slower than native arrays. Use sparingly.      *
*                                                                              *
*   This class also tries to mimic native PHP array behaviors as much as       *
*   possible -- except the ones that are inexcusably stupid.                   *
*                                                                              *
*   For push() operations, StrictArray keeps track of the highest numeric      *
*   index used in the array and increments it every time a new element is      *
*   pushed onto the array. If you unset() the last value in a StrictArray,     *
*   this maximum index value will be decremented, just like calling pop().     *
*   This behavior is different from native PHP arrays:                         *
*                                                                              *
*       $array = [0, 1, 2, 3];                                                 *
*       unset($array[3]);                                                      *
*       $array[] = 3;                                                          *
*       var_dump($array);                                                      *
*                                                                              *
*       array(4) {                                                             *
*           [0]=> int(0)                                                       *
*           [1]=> int(1)                                                       *
*           [2]=> int(2)                                                       *
*           [4]=> int(3)                                                       *
*       }                                                                      *
*                                                                              *
*   This seems silly to me, so I have decided not to copy it.                  *
*                                                                              *
*   LICENSE                                                                    *
*                                                                              *
*   Copyright (c) 2020 Rob Sheldon <rob@rescue.dev>                            *
*                                                                              *
*   Permission is hereby granted, free of charge, to any person obtaining a    *
*   copy of this software and associated documentation files (the "Software"), *
*   to deal in the Software without restriction, including without limitation  *
*   the rights to use, copy, modify, merge, publish, distribute, sublicense,   *
*   and/or sell copies of the Software, and to permit persons to whom the      *
*   Software is furnished to do so, subject to the following conditions:       *
*                                                                              *
*   The above copyright notice and this permission notice shall be included    *
*   in all copies or substantial portions of the Software.                     *
*                                                                              *
*   THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS    *
*   OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF                 *
*   MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.     *
*   IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY       *
*   CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT,       *
*   TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE          *
*   SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.                     *
*                                                                              *
*   https://opensource.org/licenses/MIT                                        *
*                                                                              *
*******************************************************************************/

namespace Asinius;


/*******************************************************************************
*                                                                              *
*   \Asinius\StrictArray                                                       *
*                                                                              *
*******************************************************************************/

class StrictArray implements \ArrayAccess, \Countable, \SeekableIterator
{

    protected $_int_keys        = [];
    protected $_str_keys        = [];
    protected $_other_keys      = [];
    protected $_values          = [];
    protected $_position        = 0;
    protected $_count           = 0;
    protected $_next_index      = 0;
    protected $_next_int_key    = 0;
    protected $_is_sequential   = true;
    protected $_case_sensitive  = true;


    /**
     * Retrieve keys and values from things that can hold keys and values, in
     * the most efficient manner available.
     * 
     * @param   mixed       $object
     *
     * @internal
     *
     * @return  array
     */
    protected static function _extract ($object)
    {
        if ( is_array($object) ) {
            return [array_keys($object), array_values($object)];
        }
        $keys = [];
        $values = [];
        if ( is_object($object) ) {
            if ( is_callable([$object, 'keys']) && is_callable([$object, 'values']) ) {
                try {
                    return [$object->keys(), $object->values()];
                } catch (Exception $e) {
                    ;
                }
            }
            if ( $object instanceof \Traversable ) {
                foreach ($object as $key => $value) {
                    $keys[] = $key;
                    $values[] = $value;
                }
                return [$keys, $values];
            }
        }
        throw new \RuntimeException("Not a traversable object: $object");
    }


    /**
     * Return the internal index for a key.
     *
     * @param   mixed       $key
     * @param   boolean     $case_sensitive
     *
     * @internal
     *
     * @return  mixed
     */
    protected function _find_key ($key, $case_sensitive = true)
    {
        if ( $this->_is_sequential ) {
            if ( is_int($key) && $key >= 0 && $key < $this->_count ) {
                return $key;
            }
            return null;
        }
        if ( is_int($key) ) {
            $i = array_search($key, $this->_int_keys, true);
        }
        else if ( is_string($key) ) {
            if ( $case_sensitive && $this->_case_sensitive ) {
                $i = array_search($key, $this->_str_keys, true);
            }
            else {
                //  Barf.
                $i = count($this->_str_keys);
                while ($i--) {
                    if ( strcasecmp($key, $this->_str_keys[$i]) === 0 ) {
                        break;
                    }
                }
                if ( $i < 0 ) {
                    $i = false;
                }
            }
        }
        else {
            $i = array_search($key, $this->_other_keys, true);
        }
        return $i === false ? null : $i;
    }


    /**
     * Return the key at the nth position in the array.
     *
     * @param   integer     $index
     *
     * @internal
     *
     * @throws  \RuntimeException
     *
     * @return  mixed
     */
    protected function _get_key_for_index ($index)
    {
        if ( $this->_is_sequential ) {
            if ( is_null($index) || ($index >= 0 && $index < $this->_count) ) {
                return $index;
            }
        }
        else {
            switch (true) {
                case (is_null($index)):
                    return null;
                case (array_key_exists($index, $this->_int_keys)):
                    return $this->_int_keys[$index];
                case (array_key_exists($index, $this->_str_keys)):
                    return $this->_str_keys[$index];
                case (array_key_exists($index, $this->_other_keys)):
                    return $this->_other_keys[$index];
            }
        }
        throw new \RuntimeException("Key not found at position $index of array");
    }


    /**
     * Return keys from the current array given their indexes.
     *
     * This is slightly faster for multiple indexes than calling _get_key_for_index()
     * iteratively.
     * 
     * @param   array       $indexes
     *
     * @internal
     *
     * @return  array
     */
    protected function _get_keys ($indexes)
    {
        if ( $this->_is_sequential ) {
            return array_map(function($index){
                if ( is_null($index) || ($index >= 0 && $index < $this->_count) ) {
                    return $index;
                }
                throw new \RuntimeException("Key not found at position $index of array");
            }, $indexes);
        }
        return array_map(function($index){
            switch (true) {
                case (is_null($index)):
                    return null;
                case (array_key_exists($index, $this->_int_keys)):
                    return $this->_int_keys[$index];
                case (array_key_exists($index, $this->_str_keys)):
                    return $this->_str_keys[$index];
                case (array_key_exists($index, $this->_other_keys)):
                    return $this->_other_keys[$index];
            }
            throw new \RuntimeException("Key not found at position $index of array");
        }, $indexes);
    }


    /**
     * Convert the current array from a "sequential" type (keys match indexes
     * in _values storage) to a non-sequential type (mixed key types, or gaps
     * in numeric keys). This will make most operations on the array much slower.
     *
     * @internal
     * 
     * @return  void
     */
    protected function _desequence ()
    {
        $this->_next_int_key = $this->_next_index = count($this->_values);
        $this->_int_keys = array_keys($this->_values);
        $this->_is_sequential = false;
    }


    /**
     * Add keys and/or values to the current array. Keys and values are expected
     * to both be arrays of the same length, unless $keys is empty. This signals
     * that sequential integer keys should be generated for the values being
     * added to internal storage.
     * 
     * @param   array       $keys
     * @param   array       $values
     * @param   boolean     $recursive
     *
     * @internal
     *
     * @throws  \RuntimeException
     * 
     * @return  void
     */
    protected function _store ($keys, $values, $recursive = false)
    {
        if ( ! empty($keys) && $keys[0] === 0 && $keys === range(0, count($keys) - 1) ) {
            //  Treat input arrays with sequential keys as though there are no
            //  keys present.
            $keys = [];
        }
        $n = count($values);
        if ( empty($keys) ) {
            if ( $this->_is_sequential ) {
                $this->_values = array_merge($this->_values, $values);
            }
            else {
                //  Fill in matching values for integer keys.
                //  Reminder: this goes [index => key, ...]
                $new_indexes = range($this->_next_index, $this->_next_index + $n - 1);
                $this->_int_keys += array_combine($new_indexes, range($this->_next_int_key, $this->_next_int_key + $n - 1));
                $this->_values += array_combine($new_indexes, $values);
                $this->_next_index += $n;
                $this->_next_int_key += $n;
            }
            $this->_count += $n;
            return;
        }
        if ( $this->_is_sequential ) {
            //  Convert this sequential array to a non-sequential array.
            $this->_desequence();
        }
        if ( count($keys) != $n ) {
            throw new \RuntimeException("Can't _store() these keys and values: the number of keys doesn't match the number of values");
        }
        //  From here, there are no more shortcuts available. Each input key
        //  must be compared to the current set of keys. This is slow and awful.
        for ( $i = 0; $i < $n; $i++ ) {
            if ( is_null($keys[$i]) ) {
                throw new \RuntimeException("Can't store a value with a null key");
            }
            $index = $this->_find_key($keys[$i]);
            if ( ! is_null($index) ) {
                if ( ! $recursive ) {
                    $this->_values[$index] = $values[$i];
                    continue;
                }
                //  This next section will attempt to merge different combinations
                //  of array-like values. If it can't, it will simply overwrite
                //  the specified value. In some cases a stored array-like value
                //  may be converted to a StrictArray.
                if ( is_array($values[$i]) ) {
                    if ( is_array($this->_values[$index]) ) {
                        $this->_values[$index] = array_replace_recursive($this->_values[$index], $values[$i]);
                        continue;
                    }
                    if ( is_object($this->_values[$index]) ) {
                        if ( is_a($this->_values[$index], '\Asinius\StrictArray') ) {
                            $this->_values[$index]->merge_recursive($values[$i]);
                            continue;
                        }
                        try {
                            $keys_and_values = static::_extract($this->_values[$index]);
                            $new = new \Asinius\StrictArray();
                            $new->combine($keys_and_values[0], $keys_and_values[1]);
                            $new->merge_recursive($values[$i]);
                            $this->_values[$index] = $new;
                            continue;
                        } catch (Exception $e) {
                            ;
                        }
                    }
                }
                if ( is_object($values[$i]) && is_a($values[$i], '\Asinius\StrictArray') ) {
                    if ( is_object($this->_values[$index]) ) {
                        $this->_values[$index]->merge_recursive($values[$i]);
                        continue;
                    }
                    if ( is_array($this->_values[$index]) ) {
                        $new = new \Asinius\StrictArray($this->_values[$index]);
                        $new->merge_recursive($values[$i]);
                        continue;
                    }
                }
                //  This is the fall-thru condition, where the existing value gets overwritten.
                $this->_values[$index] = $values[$i];
            }
            else {
                if ( is_int($keys[$i]) ) {
                    $this->_int_keys[$this->_next_index] = $keys[$i];
                    if ( $keys[$i] > $this->_next_int_key ) {
                        $this->_next_int_key = $keys[$i] + 1;
                    }
                }
                else if ( is_string($keys[$i]) ) {
                    $this->_str_keys[$this->_next_index] = $keys[$i];
                }
                else {
                    $this->_other_keys[$this->_next_index] = $keys[$i];
                }
                $this->_values[$this->_next_index++] = $values[$i];
                $this->_count++;
            }
        }
    }


    /**
     * Return a new \Asinius\StrictArray.
     * 
     * WARNING: Any arrays passed to the constructor here will already have
     * have mangled key data types. Consider instantiating an empty StrictArray
     * and calling combine() instead.
     * 
     * See the references at the top of this file for more information.
     *
     * @param   mixed
     *
     * @return  \Asinius\StrictArray
     */
    public function __construct ()
    {
        $arguments = func_get_args();
        //  Return early if called without arguments.
        if ( count($arguments) == 0 ) {
            return;
        }
        //  If called with more than one value, just import those values into
        //  the StrictArray with sequential indexes.
        if ( count($arguments) > 1 ) {
            $this->_store([], array_values($arguments));
            return;
        }
        //  If called with a single value that is an array, import the keys and
        //  values from that array.
        try {
            call_user_func_array([$this, '_store'], static::_extract($arguments[0]));
        }
        catch (\Exception $e) {
            //  If the argument isn't an array or a Traversable, just add the
            //  argument as a single element.
            $this->_store([], $arguments);
        }
    }


    /**
     * Return true if a given key exists in the StrictArray.
     *
     * @param   mixed       $key
     * @param   boolean     $case_sensitive
     * 
     * @return  boolean
     */
    public function offsetExists ($key, $case_sensitive = true)
    {
        return ! is_null($this->_find_key($key, $case_sensitive));
    }


    /**
     * Return an element, given its key.
     *
     * @param   mixed       $key
     * @param   boolean     $case_sensitive
     * 
     * @return  mixed
     */
    public function &offsetGet ($key, $case_sensitive = true)
    {
        //  https://stackoverflow.com/a/51703824
        $i = $this->_find_key($key, $case_sensitive);
        if ( is_null($i) ) {
            return $i;
        }
        return $this->_values[$i];
    }


    /**
     * Set a value with a key.
     *
     * @param   mixed       $key
     * @param   mixed       $value
     *
     * @return  void
     */
    public function offsetSet ($key, $value)
    {
        if ( is_null($key) ) {
            $this->_store([], [$value]);
        }
        else {
            $this->_store([$key], [$value]);
        }
    }


    /**
     * Delete an element, given its key.
     *
     * @param   mixed       $key
     * @param   boolean     $case_sensitive
     *
     * @return  void
     */
    public function offsetUnset ($key, $case_sensitive = true)
    {
        if ( is_null($i = $this->_find_key($key, $case_sensitive)) ) {
            return;
        }
        if ( $i < $this->_count - 1 && $this->_is_sequential ) {
            //  This operation is going to make this array non-sequential.
            $this->_desequence();
        }
        unset($this->_values[$i]);
        $this->_count--;
        if ( ! $this->_is_sequential ) {
            if ( is_int($key) && array_key_exists($i, $this->_int_keys) && $this->_int_keys[$i] === $key ) {
                unset($this->_int_keys[$i]);
            }
            else if ( is_string($key) && array_key_exists($i, $this->_str_keys) && $this->_str_keys[$i] === $key ) {
                unset($this->_str_keys[$i]);
            }
            else if ( array_key_exists($i, $this->_other_keys) && $this->_other_keys[$i] === $key ) {
                unset($this->_other_keys[$i]);
            }
            else {
                throw new \RuntimeException("Key mismatched or not found during unset: $key");
            }
        }
    }


    /**
     * Return the number of elements currently in the array.
     *
     * @return  int
     */
    public function count ()
    {
        return $this->_count;
    }


    /**
     * Return the value at the current internal pointer.
     *
     * @return  mixed
     */
    public function current ()
    {
        return current($this->_values);
    }


    /**
     * Return the key at the current position in the array.
     *
     * @return  mixed
     */
    public function key ()
    {
        return $this->_get_key_for_index(key($this->_values));
    }


    /**
     * Advance the current internal pointer position and return the value at that
     * position or false if the pointer has advanced past the end of the array.
     *
     * @return  mixed
     */
    public function next ()
    {
        if ( $this->_position < $this->_count ) {
            $this->_position++;
            return next($this->_values);
        }
        return false;
    }


    /**
     * Move the internal position backwards one element and return that
     * element's value.
     *
     * @return  mixed
     */
    public function previous ()
    {
        if ( $this->_position > 0 ) {
            $this->_position--;
            return prev($this->_values);
        }
        return false;
    }


    /**
     * Rewind the current internal pointer to the beginning of the array and
     * retrn the value there.
     *
     * @return  mixed
     */
    public function rewind ()
    {
        $this->_position = 0;
        return reset($this->_values);
    }


    /**
     * Returns true if the internal position is at a valid location in the StrictArray.
     * Used by foreach(...).
     *
     * @return  boolean
     */
    public function valid ()
    {
        return is_int($this->_position) && $this->_count > $this->_position;
    }


    /**
     * Move the internal position to a new location within the StrictArray.
     *
     * @param   int         $position
     *
     * @return  void
     */
    public function seek ($position)
    {
        if ( ! is_int($position) || $position < 0 || $position >= $this->_count ) {
            throw new \OutOfBoundsException("Can't seek() to position $position");
        }
        //  There's not a nicer way to do this. :-(
        while ( $this->_position < $position ) {
            $this->next();
        }
        while ( $this->_position > $position ) {
            $this->previous();
        }
    }


    /**
     * Same as the end() built-in: move the current internal position to the
     * last element and return that element's value.
     *
     * @return  mixed
     */
    public function end ()
    {
        $this->_position = $this->_count - 1;
        return end($this->_values);
    }


    /**
     * Add a value onto the end of the StrictArray with no key.
     *
     * @param   mixed       $value
     *
     * @return  void
     */
    public function append ($value)
    {
        $this->_store([], [$value]);
    }


    /**
     * Return the keys for this array in the order in which they were added.
     * 
     * @return  array
     */
    public function keys ()
    {
        if ( $this->_is_sequential ) {
            return range(0, $this->_count -1);
        }
        $keys = $this->_int_keys + $this->_str_keys + $this->_other_keys;
        ksort($keys);
        return array_values($keys);
    }


    /**
     * Return the current values in the StrictArray (without their keys).
     * 
     * @return  array
     */
    public function values ()
    {
        return array_values($this->_values);
    }


    /**
     * Push one or more values onto the end of the StrictArray.
     *
     * @param   mixed       $values
     *
     * @return  void
     */
    public function push (...$values)
    {
        $this->_store([], $values);
    }


    /**
     * Remove the last element from the array and return it.
     *
     * @return  mixed
     */
    public function pop ()
    {
        if ( $this->_count < 1 ) {
            return null;
        }
        $value = $this->end();
        $this->offsetUnset($this->_get_key_for_index(key($this->_values)));
        return $value;
    }


    /**
     * Safely append a set of keys and values to the current array (maintaining
     * their types and values). If any keys passed to combine() match any keys
     * already stored in the array, then the stored values will be overwritten.
     * 
     * @param   mixed   $keys
     * @param   mixed   $values
     * 
     * @return  void
     */
    public function combine ($keys, $values)
    {
        try {
            list($null, $keys) = static::_extract($keys);
        }
        catch (\Exception $e) {
            throw new \RuntimeException("Can't iterate over these keys: $keys");
        }
        try {
            list($null, $values) = static::_extract($values);
        }
        catch (\Exception $e) {
            throw new \RuntimeException("Can't iterate over these values: $values");
        }
        if ( count($keys) !== count($values) ) {
            throw new \RuntimeException("Can't append these keys and values: the counts don't match");
        }
        $this->_store($keys, $values);
    }


    /**
     * Merge one or more arrays into the current StrictArray. Any conflicting
     * keys will be overwritten.
     *
     * @param   mixed       $arrays
     *
     * @return  void
     */
    public function merge (...$arrays)
    {
        foreach ($arrays as $array) {
            try {
                list($keys, $values) = static::_extract($array);
            }
            catch (\Exception $e) {
                throw new \RuntimeException("Can't merge this");
            }
            $this->_store($keys, $values);
        }
    }


    /**
     * Recursively merge one or more arrays into the current StrictArray.
     * This behaves like PHP's array_replace_recursive() function, in which
     * conflicting scalar values will be overwritten. If both values for a given
     * key are arrays or StrictArrays, then their values will be merged.
     *
     * @param   mixed       $arrays
     *
     * @return void
     */
    public function merge_recursive (...$arrays)
    {
        foreach ($arrays as $array) {
            try {
                list($keys, $values) = static::_extract($array);
            } catch (Exception $e) {
                throw new \RuntimeException("Can't recursively merge this");
            }
            $this->_store($keys, $values, true);
        }
    }


    /**
     * Return a subset of the current array with values matching a regular
     * expression. Keys are preserved.
     *
     * @param   string      $expression
     *
     * @return  StrictArray
     */
    public function preg_search ($expression)
    {
        $values = preg_grep($expression, $this->_values);
        $keys = $this->_get_keys(array_keys($values));
        $new = new StrictArray();
        $new->combine($keys, $values);
        return $new;
    }


    /**
     * Return the keys (and their values) in this array that match a regular
     * expression.
     *
     * Currently, this only searches string keys.
     *
     * @param   string      $expression
     *
     * @return  StrictArray
     */
    public function preg_search_keys ($expression)
    {
        $matches = array_values(preg_grep($expression, $this->_str_keys));
        $new = new StrictArray();
        foreach ($matches as $key) {
            $new[$key] = $this->offsetGet($key);
        }
        return $new;
    }


    /**
     * Make string matching on array keys case-insensitive. I don't recommend
     * doing this, but here it is anyway.
     *
     * @return  void
     */
    public function set_case_insensitive ()
    {
        $this->_case_sensitive = false;
    }

}
