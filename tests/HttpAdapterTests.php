<?php

use League\Flysystem\Adapter\Http as HttpAdapter;
use League\Flysystem\Config;

class HttpAdapterTests extends PHPUnit_Framework_TestCase
{
    protected $fakeUrl = "http://any.url.com/bla/dibla/dibla";

    protected function getAdapter($expectation)
    {
        return new HttpAdapter($this->fakeUrl, $expectation);
    }

    protected function getGuzzleClient()
    {
        return Mockery::mock('GuzzleHttp\ClientInterface');
    }

    protected function getGuzzleResponse()
    {
        return Mockery::mock('GuzzleHttp\Message\ResponseInterface');
    }

    protected function getGuzzleRequest()
    {
        return Mockery::mock('\GuzzleHttp\Message\RequestInterface');
    }


    public function hasProvider()
    {
        return array(
            array(200, true),
            array(404, false),
            array('GuzzleHttp\Exception\ClientException', false),
        );
    }

    /**
     * @dataProvider  hasProvider
     * @param $codeOrException
     * @param $expectedReturnValue
     */
    public function testHas($codeOrException, $expectedReturnValue)
    {
        // prepare the client
        $clientMock = $this->getGuzzleClient();
        $clientMockExpectation = $clientMock
            ->shouldReceive('head')
            ->times(1);

        // prepare the response
        if (is_int($codeOrException)) {

            $responseMock = $this->getGuzzleResponse();
            $responseMock
                ->shouldReceive('getStatusCode')
                ->times(1)
                ->andReturnValues(
                    array($codeOrException)
                );

            $clientMockExpectation->andReturnValues(array($responseMock));

        } elseif (is_string($codeOrException)) {

            $clientMockExpectation->andThrow(new $codeOrException('Error message', $this->getGuzzleRequest()));

        }

        // test
        /** @var HttpAdapter $adapter */
        $adapter = $this->getAdapter($clientMock);
        $exists  = $adapter->has('bla/dibla/dibla');

        $this->assertEquals($exists, $expectedReturnValue);
    }


    public function readProvider()
    {
        return array(
            array('some content', '200', 'some content'),
            array(false, '404', false),
        );
    }

    /**
     * @dataProvider  readProvider
     */
    public function testRead($responseReturn, $responseCode, $expectedResponseReturn)
    {

        // prepare the response
        $responseMock        = $this->getGuzzleResponse();
        $responseMock
            ->shouldReceive('getStatusCode')
            ->times(1)
            ->andReturnValues(
                array($responseCode)
            );
        if ($responseCode === '200') {
            $responseMock
                ->shouldReceive('getBody')
                ->times(1)
                ->andReturnValues(
                    array($responseReturn)
                );
        }


        // prepare the client
        $clientMock        = $this->getGuzzleClient();
        $clientMock
            ->shouldReceive('get')
            ->times(1)
            ->andReturnValues(
                array($responseMock)
            );

        // test
        /** @var HttpAdapter $adapter */
        $adapter = $this->getAdapter($clientMock);
        $return  = $adapter->read('bla/dibla/dibla');

        $this->assertEquals($return['contents'], $expectedResponseReturn);
    }

    public function expectedFailsProvider()
    {
        return array(
            array('write'),
            array('writeStream'),
            array('updateStream'),
            array('update'),
            array('rename'),
            array('copy'),
            array('delete'),
            array('listContents'),
            array('deleteDir'),
        );
    }

    /**
     * @dataProvider expectedFailsProvider
     */
    public function testExpectedFails($method)
    {
        $adapter = new HttpAdapter($this->fakeUrl);
        $this->assertFalse($adapter->{$method}('one', 'two', new Config));
    }

    public function testExpectedFailCreateDir()
    {
        $adapter = new HttpAdapter($this->fakeUrl);
        $this->assertFalse($adapter->createDir('one', new Config));
    }


    public function testBuildUrl()
    {
        $adapter = new HttpAdapter($this->fakeUrl);

        $class = new \ReflectionClass($adapter);
        $method = $class->getMethod("buildUrl");
        $method->setAccessible(true);

        $filename = "file1.txt";
        $url = $method->invokeArgs($adapter, [$filename]);
        $this->assertSame($this->fakeUrl . "/" . $filename, $url);
    }
}
