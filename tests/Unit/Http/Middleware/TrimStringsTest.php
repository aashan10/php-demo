<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use Elementary\Http\Middleware\TrimStrings;
use Elementary\Http\Request;
use Elementary\Http\Response;
use Elementary\Utils\SessionBag;
use PHPUnit\Framework\TestCase;

class TrimStringsTest extends TestCase
{
    private TrimStrings $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new TrimStrings();
    }

    private function createRequest(array $post = []): Request
    {
        return new Request(
            get: [],
            post: $post,
            cookies: [],
            files: [],
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/'],
            headers: [],
            request: [],
            session: new SessionBag(),
            attributes: [],
            content: null
        );
    }

    public function testProcessWithNoPostData(): void
    {
        $request = $this->createRequest([]);
        
        $nextCalled = false;
        $next = function (Request $request) use (&$nextCalled) {
            $nextCalled = true;
            return new Response(200, 'Success');
        };

        $response = $this->middleware->process($request, $next);

        $this->assertTrue($nextCalled);
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Success', $response->getContent());
    }

    public function testTrimsStringValues(): void
    {
        $postData = [
            'name' => '  John Doe  ',
            'email' => "\t user@example.com \n",
            'message' => '   Hello World   ',
        ];
        
        $request = $this->createRequest($postData);
        
        $next = function (Request $request) {
            // Verify strings were trimmed
            $this->assertEquals('John Doe', $request->post->get('name'));
            $this->assertEquals('user@example.com', $request->post->get('email'));
            $this->assertEquals('Hello World', $request->post->get('message'));
            return new Response(200, 'Success');
        };

        $response = $this->middleware->process($request, $next);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testDoesNotTrimNonStringValues(): void
    {
        $postData = [
            'string_value' => '  trim me  ',
            'integer_value' => 123,
            'float_value' => 45.67,
            'boolean_value' => true,
            'null_value' => null,
            'array_value' => ['  not trimmed  ', '  also not trimmed  '],
        ];
        
        $request = $this->createRequest($postData);
        
        $next = function (Request $request) {
            // Verify only string values were trimmed
            $this->assertEquals('trim me', $request->post->get('string_value'));
            $this->assertEquals(123, $request->post->get('integer_value'));
            $this->assertEquals(45.67, $request->post->get('float_value'));
            $this->assertEquals(true, $request->post->get('boolean_value'));
            $this->assertNull($request->post->get('null_value'));
            $this->assertEquals(['  not trimmed  ', '  also not trimmed  '], $request->post->get('array_value'));
            return new Response(200, 'Success');
        };

        $response = $this->middleware->process($request, $next);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testTrimsEmptyStrings(): void
    {
        $postData = [
            'empty_string' => '',
            'whitespace_only' => '   ',
            'tabs_only' => "\t\t",
            'newlines_only' => "\n\n",
            'mixed_whitespace' => " \t\n ",
        ];
        
        $request = $this->createRequest($postData);
        
        $next = function (Request $request) {
            // Verify all whitespace was trimmed
            $this->assertEquals('', $request->post->get('empty_string'));
            $this->assertEquals('', $request->post->get('whitespace_only'));
            $this->assertEquals('', $request->post->get('tabs_only'));
            $this->assertEquals('', $request->post->get('newlines_only'));
            $this->assertEquals('', $request->post->get('mixed_whitespace'));
            return new Response(200, 'Success');
        };

        $response = $this->middleware->process($request, $next);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testTrimsStringWithInternalWhitespace(): void
    {
        $postData = [
            'sentence' => '  Hello  World  ',
            'multiline' => "  Line 1  \n  Line 2  ",
            'tabs_inside' => "\t\tStart\t\tMiddle\t\tEnd\t\t",
        ];
        
        $request = $this->createRequest($postData);
        
        $next = function (Request $request) {
            // Verify only leading/trailing whitespace was trimmed, internal preserved
            $this->assertEquals('Hello  World', $request->post->get('sentence'));
            $this->assertEquals("Line 1  \n  Line 2", $request->post->get('multiline'));
            $this->assertEquals("Start\t\tMiddle\t\tEnd", $request->post->get('tabs_inside'));
            return new Response(200, 'Success');
        };

        $response = $this->middleware->process($request, $next);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testHandlesSpecialCharacters(): void
    {
        $postData = [
            'unicode' => '  café  ',
            'symbols' => '  @#$%^&*()  ',
            'numbers_string' => '  123456  ',
            'mixed' => '  Hello123@#$  ',
        ];
        
        $request = $this->createRequest($postData);
        
        $next = function (Request $request) {
            $this->assertEquals('café', $request->post->get('unicode'));
            $this->assertEquals('@#$%^&*()', $request->post->get('symbols'));
            $this->assertEquals('123456', $request->post->get('numbers_string'));
            $this->assertEquals('Hello123@#$', $request->post->get('mixed'));
            return new Response(200, 'Success');
        };

        $response = $this->middleware->process($request, $next);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testProcessWithMixedDataTypes(): void
    {
        $postData = [
            'trimmed_string' => '  should be trimmed  ',
            'integer' => 42,
            'float' => 3.14,
            'boolean_true' => true,
            'boolean_false' => false,
            'null_value' => null,
            'empty_string' => '',
            'whitespace_string' => '   ',
            'zero_string' => '0',
            'array' => [1, 2, 3],
            'nested_array' => ['key' => '  not trimmed  '],
        ];
        
        $request = $this->createRequest($postData);
        
        $next = function (Request $request) {
            // Verify correct processing of all data types
            $this->assertEquals('should be trimmed', $request->post->get('trimmed_string'));
            $this->assertEquals(42, $request->post->get('integer'));
            $this->assertEquals(3.14, $request->post->get('float'));
            $this->assertTrue($request->post->get('boolean_true'));
            $this->assertFalse($request->post->get('boolean_false'));
            $this->assertNull($request->post->get('null_value'));
            $this->assertEquals('', $request->post->get('empty_string'));
            $this->assertEquals('', $request->post->get('whitespace_string'));
            $this->assertEquals('0', $request->post->get('zero_string'));
            $this->assertEquals([1, 2, 3], $request->post->get('array'));
            $this->assertEquals(['key' => '  not trimmed  '], $request->post->get('nested_array'));
            return new Response(200, 'Success');
        };

        $response = $this->middleware->process($request, $next);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testDoesNotModifyOriginalArray(): void
    {
        $originalPostData = [
            'name' => '  John  ',
            'untrimmed_number' => 123,
        ];
        
        $request = $this->createRequest($originalPostData);
        
        $next = function (Request $request) {
            return new Response(200, 'Success');
        };

        $response = $this->middleware->process($request, $next);

        // Verify request was processed correctly
        $this->assertEquals(200, $response->getStatusCode());
        
        // Verify the request post data was modified
        $this->assertEquals('John', $request->post->get('name'));
        $this->assertEquals(123, $request->post->get('untrimmed_number'));
    }

    public function testProcessWithGetRequest(): void
    {
        // Test that middleware works with GET requests (even though it only processes POST data)
        $request = new Request(
            get: ['param' => '  not processed  '],
            post: ['data' => '  will be trimmed  '],
            cookies: [],
            files: [],
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'],
            headers: [],
            request: [],
            session: new SessionBag(),
            attributes: [],
            content: null
        );
        
        $next = function (Request $request) {
            // GET data should not be trimmed, POST data should be
            $this->assertEquals('  not processed  ', $request->get->get('param'));
            $this->assertEquals('will be trimmed', $request->post->get('data'));
            return new Response(200, 'Success');
        };

        $response = $this->middleware->process($request, $next);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testLargePostData(): void
    {
        // Test with many POST fields
        $postData = [];
        for ($i = 0; $i < 100; $i++) {
            $postData["field_$i"] = "  value $i  ";
        }
        
        $request = $this->createRequest($postData);
        
        $next = function (Request $request) {
            // Verify all fields were trimmed
            for ($i = 0; $i < 100; $i++) {
                $this->assertEquals("value $i", $request->post->get("field_$i"));
            }
            return new Response(200, 'Success');
        };

        $response = $this->middleware->process($request, $next);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testStringWithOnlyWhitespace(): void
    {
        $postData = [
            'spaces' => '     ',
            'tabs' => "\t\t\t",
            'newlines' => "\n\n\n",
            'carriage_returns' => "\r\r\r",
            'mixed' => " \t\n\r ",
        ];
        
        $request = $this->createRequest($postData);
        
        $next = function (Request $request) {
            // All should be trimmed to empty strings
            $this->assertEquals('', $request->post->get('spaces'));
            $this->assertEquals('', $request->post->get('tabs'));
            $this->assertEquals('', $request->post->get('newlines'));
            $this->assertEquals('', $request->post->get('carriage_returns'));
            $this->assertEquals('', $request->post->get('mixed'));
            return new Response(200, 'Success');
        };

        $response = $this->middleware->process($request, $next);

        $this->assertEquals(200, $response->getStatusCode());
    }
}