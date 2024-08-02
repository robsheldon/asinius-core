# IOBuffer

`IOBuffer` is a low-level class that provides support for streaming reads and writes on file-backed blocks of memory.

## Usage


## Description

`IOBuffer` uses PHP's [`php://temp`](https://www.php.net/manual/en/wrappers.php.php#wrappers.php.memory) feature to provide file-backed general-purpose data storage. When an `IOBuffer`'s interanl data storage exceeds either 2MB (by default) or the internal `/maxmemory`.

`IOBuffer` is intended to be used by `Datastream` to buffer data from pipes, network sockets, and other data interfaces that are bursty and ephemeral. `IOBuffer` allows callers to collect data from these interfaces whenever it's available, and then pass the data back to the application on demand. Because they are file-backed, their storage is limited only by the allowances of the PHP runtime environment or the operating system.

`IOBuffer`s behave like FIFO data queues. Data written in to the `IOBuffer` is appended to the end of the buffer, regardless of the buffer's current read position.


## Reference


## TODO

* Add static class support for the `/maxmemory` parameter
* Ensure that `sys_get_temp_dir()` returns a valid value on startup
* Documentation
* Multibyte support (already in progress)
* Add a `IOBuffer::ROTATE_AT_SIZE` static value and matching method. If either the static class value or the object's corresponding property is set, (greater than 0), then the `IOBuffer` will "wraparound" subsequent writes, erasing some of the read history. If the write index reaches the read index, then the read index is moved forward with subsequent writes (warning! data loss!). This can be an alternative to setting `/maxmemory` or dealing with `sys_get_temp_dir()`.
* Add `copy_from_resource`  and `copy_to_resource` methods
* add a `get_read_index()` function (like position tracking, but just returns the current read index)
* May need to switch to line length tracking anyway for compatibility with Multibyte IOBuffers:
```php
$position = $this->_read_position;
foreach ($this->_lines as $line => $length) {
    if ( $length > $position ) {
        break;
    }
    $position -= $length;
}
return [$line + 1, $position];
```
