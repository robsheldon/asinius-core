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
        $this->assertNull($buffer->read(1));
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
        $iobuffer = new IOBuffer();
        for ($i = 0; $i < 1024; $i++) {
            $byte = $iobuffer->read(1, function($buffer, $count) use (&$i, $data_in){
                $next = substr($data_in, $i, $count);
                $buffer->append($next);
            });
            if ( $byte === null ) {
                //  It shouldn't be. But also, don't need to unnecessarily add 1024
                //  assertions here.
                $this->assertNotNull($byte);
            }
            $data_out .= $byte;
        }
        $this->assertSame($data_in, $data_out);
        $this->assertNull($iobuffer->read(1));
    }


    public function test_read_lines (): void
    {
        //  Generate 24 lines of some number of characters each.
        $lines_in = [];
        for ( $i = 0; $i < 24; $i++ ) {
            $lines_in[] = $this->generate_random_data(random_int(50, 150)) . "\n";
        }
        $iobuffer = new IOBuffer();
        $iobuffer->mode(IOBuffer::LINEMODE);
        $iobuffer->append(implode('', $lines_in));
        for ( $i = 0; $i < 24; $i++ ) {
            $this->assertSame($lines_in[$i], $iobuffer->read());
        }
        $this->assertNull($iobuffer->read(1));
    }
}
