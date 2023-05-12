<?php
declare(strict_types=1);

namespace Chialab\CakeObjectStorage\Test\TestCase\Model\Entity;

use Cake\Core\Container;
use Cake\I18n\FrozenTime;
use Cake\TestSuite\TestCase;
use Chialab\CakeObjectStorage\Model\Entity\File;
use Chialab\ObjectStorage\InMemoryAdapter;
use Chialab\ObjectStorage\MultipartUploadInterface;

/**
 * Tests for {@see \Chialab\CakeObjectStorage\Model\Entity\File} class.
 *
 * @coversDefaultClass \Chialab\CakeObjectStorage\Model\Entity\File
 */
class FileTest extends TestCase
{
    /**
     * Test subject
     */
    protected File $File;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->File = new File();
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        unset($this->File);

        parent::tearDown();
    }

    /**
     * Test {@see File::_getIsFinalized()} getter.
     *
     * @return void
     * @covers ::_getIsFinalized()
     */
    public function testGetIsFinalized(): void
    {
        static::assertFalse($this->File->is_finalized);
        $this->File->finalized = FrozenTime::now();
        static::assertTrue($this->File->is_finalized);
    }

    /**
     * Test {@see File::_getIsMultipart()} getter.
     *
     * @return void
     * @covers ::_getIsMultipart()
     */
    public function testGetIsMultipart(): void
    {
        static::assertFalse($this->File->is_multipart);
        $this->File->multipart_token = 'aaabbbcccddd';
        static::assertTrue($this->File->is_multipart);
        $this->File->finalized = FrozenTime::now();
        static::assertNull($this->File->is_multipart);
    }

    /**
     * Test {@see File::_getUrl()} getter.
     *
     * @return void
     * @covers ::_getUrl()
     */
    public function testGetUrl(): void
    {
        $container = new Container();
        $container->addShared(MultipartUploadInterface::class, InMemoryAdapter::class)
            ->addArguments(['https://static.example.com/']);
        /** @var \Chialab\CakeObjectStorage\Model\Table\FilesTable $table */
        $table = $this->fetchTable('Chialab/CakeObjectStorage.Files');
        $table->setContainer($container);

        $this->File->id = 'aaabbbcccddd';
        $this->File->filename = 'example.jpg';
        $this->File->finalized = FrozenTime::now();

        $url = $this->File->url;
        $expected = sprintf('https://static.example.com/%s/%s', $this->File->id, $this->File->filename);
        static::assertEquals($expected, $url);
    }
}
