<?php

namespace Asinius\tests\units;

use atoum;

class ResourceDatastream extends atoum
{

    private $_files = [];

    public function __construct ()
    {
        //  ?
        $this->_files['01.txt'] = [
            'path'      => realpath(dirname(__FILE__) . '/../data/01.txt'),
            'raw_data'  => "1\n2 a\n3 b\n4 5 6\n",
            'line_data' => ['1', '2 a', '3 b', '4 5 6'],
            'char_data' => ['1', "\n", '2', ' ', 'a', "\n", '3', ' ', 'b', "\n", '4', ' ', '5', ' ', '6', "\n"],
        ];
        parent::__construct();
    }


    public function getFileByPath ($which)
    {
        if ( ! array_key_exists($which, $this->_files) ) {
            throw new \RuntimeException("Error in ResourceDatastream test suite: $which is not in the list of data files");
        }
        return new \Asinius\ResourceDatastream($this->_files[$which]['path']);
    }


    public function testFileOpen ()
    {
        foreach ($this->_files as $filename => $fileinfo) {
            $file = $this->getFileByPath($filename);
            $this->object($file)->isInstanceOf('\Asinius\ResourceDatastream');
            $file->open();
            $this->boolean($file->ready())->isTrue();
            $file->close();
            $this->boolean($file->ready())->isFalse();
        }
    }


    public function testFileReads ()
    {
        foreach ($this->_files as $filename => $fileinfo) {
            foreach (['raw', 'line', 'char'] as $mode) {
                //  peek() followed by read().
                $file = $this->getFileByPath($filename);
                $file->set(['mode' => $mode]);
                if ( is_string($fileinfo["{$mode}_data"]) ) {
                    $n = strlen($fileinfo["{$mode}_data"]);
                }
                $peek_data = $file->peek($n);
                $read_data = $file->read($n);
                $file->close();
                if ( is_string($fileinfo["{$mode}_data"]) ) {
                    $this->string($peek_data)->isEqualTo($read_data);
                    $this->string($read_data)->isEqualTo($fileinfo["{$mode}_data"]);
                }
                else {
                    $this->array($peek_data)->isEqualTo($read_data);
                    $this->array($read_data)->isEqualTo($fileinfo["{$mode}_data"]);
                }
                //  read() without peek().
                $file = $this->getFileByPath($filename);
                $file->set(['mode' => $mode]);
                $read_chars = $file->read($n);
                $file->close();
                if ( is_string($fileinfo["{$mode}_data"]) ) {
                    $this->string($read_data)->isEqualTo($peek_data);
                }
                else {
                    $this->array($read_data)->isEqualTo($peek_data);
                }
            }
        }
    }


    public function testModeSwitching ()
    {
        $file = $this->getFileByPath('01.txt');
        $file->set(['mode' => 'raw']);
        $data = $file->read(3);
        $this->string($data)->isEqualTo("1\n2");
        $file->set(['mode' => 'line']);
        $data = $file->read(1);
        $this->array($data)->isEqualTo([' a']);
        $file->set(['mode' => 'char']);
        $data = $file->read(2);
        $this->array($data)->isEqualTo(['3', ' ']);
        $data = $file->peek(4);
        $this->array($data)->isEqualTo(['b', "\n", '4', ' ']);
        $file->set(['mode' => 'raw']);
        $data = $file->read(1);
        $this->string($data)->isEqualTo('b');
        $file->close();
    }


    public function testPositionTracking ()
    {
        $file = $this->getFileByPath('01.txt');
        $file->set(['mode' => 'char', 'tracking' => true]);
        //  Starts at line 0, position 0.
        $this->array($file->position())->isEqualTo(['line' => 0, 'position' => 0]);
        //  peek() does not advance the position info.
        $file->peek(4);
        $this->array($file->position())->isEqualTo(['line' => 0, 'position' => 0]);
        $data = $file->read(1);
        $this
            ->array($file->position())->isEqualTo(['line' => 1, 'position' => 1])
            ->array($data)->isEqualTo(['1']);
        $data = $file->read(1);
        $this
            ->array($file->position())->isEqualTo(['line' => 2, 'position' => 0])
            ->array($data)->isEqualTo(["\n"]);
        $data = $file->read(1);
        $this
            ->array($file->position())->isEqualTo(['line' => 2, 'position' => 1])
            ->array($data)->isEqualTo(['2']);
        $data = $file->read(5);
        $this
            ->array($file->position())->isEqualTo(['line' => 3, 'position' => 2])
            ->array($data)->isEqualTo([' ', 'a', "\n", '3', ' ']);
        $data = $file->read(20);
        $this
            ->array($file->position())->isEqualTo(['line' => 5, 'position' => 0])
            ->array($data)->isEqualTo(['b', "\n", '4', ' ', '5', ' ', '6', "\n"]);
        $file->close();
    }

}
