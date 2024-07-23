<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Asinius\IOBuffer;

final class IOBufferTest extends TestCase
{

    /**
     * Generate random test data of $size bytes.
     *
     * @param int $size
     *
     * @throws Exception
     *
     * @return string
     */
    private function generate_random_data (int $size): string
    {
        $pool = '';
        $pool_size = 0;
        $tries = 0;
        while (strlen($pool) < $size) {
            try {
                $pool .= preg_replace('/[^!-~]/', '', random_bytes(256));
            }
            catch (Exception $e) {
            }
            if ( strlen($pool) === $pool_size ) {
                $tries++;
                if ( $tries > 4 ) {
                    throw new Exception("Can't add random data to pool: random_bytes() is repeatedly throwing an error");
                }
            }
            else {
                $tries = 0;
                $pool_size = strlen($pool);
            }
        }
        return substr($pool, 0, $size);
    }


    /**
     * Simplest test iteration: immediately append a blob of data into an IOBUffer
     * and then read it all back out again in one call.
     *
     * @throws Exception
     *
     * @return void
     */
    public function test_raw_read (): void
    {
        $data_in = $this->generate_random_data(1024);
        $buffer = new IOBuffer();
        $buffer->append($data_in);
        $data_out = $buffer->read(1024);
        $this->assertSame($data_in, $data_out);
        //  Should be no more data available.
        $this->assertSame('', $buffer->read(1));
    }


    /**
     * Append random data one byte at a time to an IOBuffer and read it back again.
     *
     * @return void
     *
     * @throws Exception
     */
    public function test_raw_read_sequential_bytes (): void
    {
        //  Read a randomly-generated 1K pool of bytes into the IOBuffer and
        //  back out again.
        $data_in = $this->generate_random_data(1024);
        $data_out = '';
        $iobuffer = new IOBuffer(function($buffer, $count) use (&$i, $data_in){
            $next = substr($data_in, $i, $count);
            $buffer->append($next);
        });
        for ($i = 0; $i < 1024; $i++) {
            $byte = $iobuffer->read(1);
            if ( $byte === '' ) {
                //  It shouldn't be. But also, don't need to unnecessarily add 1024
                //  assertions here.
                $this->assertNotEmpty($byte);
            }
            $data_out .= $byte;
        }
        $this->assertSame($data_in, $data_out);
        $this->assertSame('', $iobuffer->read(1));
    }


    /**
     * Verify basic functionality for the peek_line() function.
     *
     * @return void
     */
    public function test_peek_line (): void
    {
        $iobuffer = new IOBuffer();
        $iobuffer->append(file_get_contents(implode(DIRECTORY_SEPARATOR, [__DIR__, '..', 'data', 'thanatopsis.txt'])));
        //  Compare the first line of the test file.
        $this->assertSame("To him, who in the love of nature holds\n", $iobuffer->peek_line());
        //  The same peek_line() operation should return the same line.
        $this->assertSame("To him, who in the love of nature holds\n", $iobuffer->peek_line());
        //  Read a few chars, and the next peek_line() operation should return the rest of the line.
        $iobuffer->read(10);
        $this->assertSame("o in the love of nature holds\n", $iobuffer->peek_line());
    }


    /**
     * Verify basic functionality for the read_line() function.
     *
     * @throws Exception
     * @throws \Random\RandomException
     *
     * @return void
     */
    public function test_read_line (): void
    {
        //  Generate 24 lines of some number of characters each.
        $lines_in = [];
        for ( $i = 0; $i < 24; $i++ ) {
            $lines_in[] = $this->generate_random_data(random_int(50, 150)) . "\n";
        }
        $iobuffer = new IOBuffer();
        $iobuffer->append(implode('', $lines_in));
        for ( $i = 0; $i < 24; $i++ ) {
            $this->assertSame($lines_in[$i], $iobuffer->read_line());
        }
        $this->assertSame('', $iobuffer->read());
    }


    /**
     * Test the line-and-character position tracking built in to IOBuffer.
     *
     * This also tests all of the peek(), peek_line(), read(), and read_line()
     * functions.
     *
     * @return void
     */
    public function test_position_tracking (): void
    {
        $iobuffer = new IOBuffer();
        $iobuffer->append(file_get_contents(implode(DIRECTORY_SEPARATOR, [__DIR__, '..', 'data', 'thanatopsis.txt'])));
        //  Initial position before any reads should be 0, 0.
        $this->assertSame(['line' => 0, 'position' => 0], $iobuffer->get_position());
        //  Read the first character and check position information.
        $this->assertSame('T', $iobuffer->read());
        $this->assertSame(['line' => 1, 'position' => 1], $iobuffer->get_position());
        //  Peek at the next character and verify that the position hasn't changed.
        $this->assertSame('o', $iobuffer->peek());
        $this->assertSame(['line' => 1, 'position' => 1], $iobuffer->get_position());
        $this->assertSame('o', $iobuffer->peek());
        //  Read the next 10 characters and verify that the position was correctly updated.
        $this->assertSame('o him, who', $iobuffer->read(10));
        $this->assertSame(['line' => 1, 'position' => 11], $iobuffer->get_position());
        //  Peek at the rest of the line.
        $this->assertSame(" in the love of nature holds\n", $iobuffer->peek_line());
        $this->assertSame(['line' => 1, 'position' => 11], $iobuffer->get_position());
        //  Read the rest of the line. There are 40 characters in this line including the "\n".
        $this->assertSame(" in the love of nature holds\n", $iobuffer->read_line());
        $this->assertSame(['line' => 1, 'position' => 40], $iobuffer->get_position());
        //  Peek at the next character (first character of next line).
        $this->assertSame('c', $iobuffer->peek(1));
        $this->assertSame(['line' => 1, 'position' => 40], $iobuffer->get_position());
        //  Read the next line.
        $this->assertSame("communion with her visible forms, she speaks\n", $iobuffer->read_line());
        $this->assertSame(['line' => 2, 'position' => 45], $iobuffer->get_position());
        //  Read 20 characters of the third line.
        $this->assertSame("a various language; ", $iobuffer->read(20));
        $this->assertSame(['line' => 3, 'position' => 20], $iobuffer->get_position());
        //  Read 85 characters more, which should go a couple of lines down.
        $this->assertSame("for his gayer hours\nshe has a voice of gladness, and a smile\nand eloquence of beauty,", $iobuffer->read(85));
        $this->assertSame(['line' => 5, 'position' => 24], $iobuffer->get_position());
    }
}
