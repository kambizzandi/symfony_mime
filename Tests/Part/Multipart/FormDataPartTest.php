<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mime\Tests\Part\Multipart;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Component\Mime\Part\TextPart;

class FormDataPartTest extends TestCase
{
    public function testConstructor()
    {
        $r = new \ReflectionProperty(TextPart::class, 'encoding');
        $r->setAccessible(true);

        $b = new TextPart('content');
        $c = DataPart::fromPath($file = __DIR__.'/../../Fixtures/mimetypes/test.gif');
        $f = new FormDataPart([
            'foo' => $content = 'very very long content that will not be cut even if the length is way more than 76 characters, ok?',
            'bar' => clone $b,
            'baz' => clone $c,
        ]);
        $this->assertEquals('multipart', $f->getMediaType());
        $this->assertEquals('form-data', $f->getMediaSubtype());
        $t = new TextPart($content, 'utf-8', 'plain', '8bit');
        $t->setDisposition('form-data');
        $t->setName('foo');
        $t->getHeaders()->setMaxLineLength(PHP_INT_MAX);
        $b->setDisposition('form-data');
        $b->setName('bar');
        $b->getHeaders()->setMaxLineLength(PHP_INT_MAX);
        $r->setValue($b, '8bit');
        $c->setDisposition('form-data');
        $c->setName('baz');
        $c->getHeaders()->setMaxLineLength(PHP_INT_MAX);
        $r->setValue($c, '8bit');
        $this->assertEquals([$t, $b, $c], $f->getParts());
    }

    public function testNestedArrayParts()
    {
        $p1 = new TextPart('content', 'utf-8', 'plain', '8bit');
        $f = new FormDataPart([
            'foo' => clone $p1,
            'bar' => [
                'baz' => [
                    clone $p1,
                    'qux' => clone $p1,
                ],
            ],
        ]);

        $this->assertEquals('multipart', $f->getMediaType());
        $this->assertEquals('form-data', $f->getMediaSubtype());

        $p1->setName('foo');
        $p1->setDisposition('form-data');

        $p2 = clone $p1;
        $p2->setName('bar[baz][0]');

        $p3 = clone $p1;
        $p3->setName('bar[baz][qux]');

        $this->assertEquals([$p1, $p2, $p3], $f->getParts());
    }

    public function testToString()
    {
        $p = DataPart::fromPath($file = __DIR__.'/../../Fixtures/mimetypes/test.gif');
        $this->assertEquals(base64_encode(file_get_contents($file)), $p->bodyToString());
    }

    public function testContentLineLength()
    {
        $f = new FormDataPart([
            'foo' => new DataPart($foo = str_repeat('foo', 1000), 'foo.txt', 'text/plain'),
            'bar' => $bar = str_repeat('bar', 1000),
        ]);
        $parts = $f->getParts();
        $this->assertEquals($foo, $parts[0]->bodyToString());
        $this->assertEquals($bar, $parts[1]->bodyToString());
    }

    public function testBoundaryContentTypeHeader()
    {
        $f = new FormDataPart([
            'file' => new DataPart('data.csv', 'data.csv', 'text/csv'),
        ]);
        $headers = $f->getPreparedHeaders()->toArray();
        $this->assertMatchesRegularExpression(
            '/^Content-Type: multipart\/form-data; boundary=[a-zA-Z0-9\-_]{8}$/',
            $headers[0]
        );
    }
}
