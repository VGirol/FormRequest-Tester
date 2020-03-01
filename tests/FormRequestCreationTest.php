<?php

declare(strict_types=1);

namespace VGirol\FormRequestTester\Tests;

use Illuminate\Foundation\Http\FormRequest;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use PHPUnit\Framework\Assert as PHPUnit;
use VGirol\FormRequestTester\TestFormRequests;

class FormRequestCreationTest extends TestCase
{
    private $tester;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tester = $this->getTester();
    }

    private function getTester()
    {
        return new class($this->app) extends OrchestraTestCase {
            use TestFormRequests;

            public function __construct($app)
            {
                parent::__construct(null, [], '');
                $this->app = $app;
            }
        };
    }

    private function getFactory($methods = [])
    {
        return function (string $formRequestType, array $args) use ($methods) {
            $mock = \call_user_func_array(
                [$this, 'getMockForAbstractClass'],
                \array_merge(
                    [$formRequestType, $args],
                    ['', true, true, true, \array_keys($methods)]
                )
            );

            foreach ($methods as $name => $returnValue) {
                $mock->expects($this->once())
                    ->method($name)
                    ->willReturn($returnValue);
            }

            return $mock;
        };
    }

    /**
     * @test
     */
    public function createFormRequestWithDefaultParameters()
    {
        $formRequest = $this->tester->createFormRequest(FormRequest::class);

        PHPUnit::assertInstanceOf(FormRequest::class, $formRequest);

        PHPUnit::assertEquals('POST', $formRequest->method());
        PHPUnit::assertEquals('fake-route', $formRequest->path());
        PHPUnit::assertNull($formRequest->route());
        PHPUnit::assertEquals([], $formRequest->all());
    }

    /**
     * @test
     */
    public function createFormRequestWithCustomParameters()
    {
        $data = [
            'key' => 'value'
        ];
        $route = 'custom-route';
        $method = 'POST';
        $options = [
            'route' => "/{$route}",
            'method' => $method
        ];

        $formRequest = $this->tester->createFormRequest(FormRequest::class, $data, $options);

        PHPUnit::assertInstanceOf(FormRequest::class, $formRequest);

        PHPUnit::assertEquals($method, $formRequest->method());
        PHPUnit::assertEquals($route, $formRequest->path());
        PHPUnit::assertNull($formRequest->route());
        PHPUnit::assertEquals($data, $formRequest->all());
    }

    /**
     * @test
     */
    public function createFormRequestMockWithDefaultParameters()
    {
        $formRequest = $this->tester->createFormRequestMock(FormRequest::class);

        PHPUnit::assertInstanceOf(FormRequest::class, $formRequest);

        PHPUnit::assertEquals('POST', $formRequest->method());
        PHPUnit::assertEquals('fake-route', $formRequest->path());
        PHPUnit::assertNull($formRequest->route());
        PHPUnit::assertEquals([], $formRequest->all());
    }

    /**
     * @test
     */
    public function createFormRequestMockWithCustomParameters()
    {
        $data = [
            'key' => 'value'
        ];
        $route = 'custom-route';
        $method = 'POST';
        $options = [
            'route' => "/{$route}",
            'method' => $method
        ];
        $mockClassName = 'testMock';
        $factory = [
            $mockClassName,
            true,
            true,
            true,
            [],
            false
        ];

        $formRequest = $this->tester->createFormRequestMock(FormRequest::class, $data, $options, $factory);

        PHPUnit::assertInstanceOf(FormRequest::class, $formRequest);
        PHPUnit::assertInstanceOf($mockClassName, $formRequest);

        PHPUnit::assertEquals($method, $formRequest->method());
        PHPUnit::assertEquals($route, $formRequest->path());
        PHPUnit::assertNull($formRequest->route());
        PHPUnit::assertEquals($data, $formRequest->all());
    }

    /**
     * @test
     */
    public function createFormRequestMockWithCustomFactory()
    {
        $class = new \ReflectionClass(FormRequest::class);
        $property = $class->getProperty('requestFactory');
        $property->setAccessible(true);

        $data = [
            'key' => 'value'
        ];
        $route = 'custom-route';
        $method = 'POST';
        $options = [
            'route' => "/{$route}",
            'method' => $method
        ];
        $mock = FormRequest::create($options['route'], $options['method'], $data);
        $factory = function () use ($mock) {
            return $mock;
        };

        $formRequest = $this->tester->createFormRequestMock(FormRequest::class, $data, $options, $factory);

        PHPUnit::assertSame($mock, $formRequest);

        PHPUnit::assertEquals($method, $formRequest->method());
        PHPUnit::assertEquals($route, $formRequest->path());
        PHPUnit::assertEquals($data, $formRequest->all());
        PHPUnit::assertNull($property->getValue($formRequest));
    }
}
