<?php
declare(strict_types=1);

namespace Chialab\CakeObjectStorage\Test\TestCase\Model\Table;

use Cake\Core\Container;
use Cake\TestSuite\TestCase;
use Chialab\CakeObjectStorage\Model\Table\FilesTable;
use Chialab\ObjectStorage\FileObject;
use Chialab\ObjectStorage\FilePart;
use Chialab\ObjectStorage\InMemoryAdapter;
use Chialab\ObjectStorage\MultipartUploadInterface;
use GuzzleHttp\Psr7\Stream;

/**
 * Tests for {@see \Chialab\CakeObjectStorage\Model\Table\FilesTable} class.
 *
 * @coversDefaultClass \Chialab\CakeObjectStorage\Model\Table\FilesTable
 */
class FilesTableTest extends TestCase
{
    /**
     * @var string[]
     */
    protected $fixtures = ['plugin.Chialab/CakeObjectStorage.Files'];

    /**
     * Test subject
     */
    protected FilesTable $Files;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $container = new Container();
        $container->addShared(MultipartUploadInterface::class, InMemoryAdapter::class)
            ->addArguments(['https://static.example.com/']);
        $container->extend(MultipartUploadInterface::class)
            ->setConcrete(fn (): InMemoryAdapter => new class ('https://static.example.com/') extends InMemoryAdapter {
                public function getMultipart(): array
                {
                    return $this->multipart;
                }
            });
        /** @var \Chialab\CakeObjectStorage\Model\Table\FilesTable $table */
        $table = $this->fetchTable('Chialab/CakeObjectStorage.Files');
        $table->setContainer($container);
        $this->Files = $table;
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        unset($this->Files);

        parent::tearDown();
    }

    /**
     * Test {@see FilesTable::beforeSave()} method.
     *
     * @return void
     * @covers ::beforeSave()
     */
    public function testBeforeSave(): void
    {
        $file = $this->Files->newEmptyEntity();
        $file->filename = 'example.jpg';
        $file->mime_type = 'image/jpg';
        $file->size = 1024;
        $this->Files->saveOrFail($file);
        static::assertNotEmpty($file->id);
        static::assertEmpty($file->multipart_token);

        $file = $this->Files->newEmptyEntity();
        $file->filename = 'example.jpg';
        $file->mime_type = 'image/jpg';
        $file->size = 20 << 20; // 20 MiB
        $this->Files->saveOrFail($file);
        static::assertNotEmpty($file->id);
        static::assertNotEmpty($file->multipart_token);
    }

    /**
     * Test {@see FilesTable::afterDeleteCommit()} method.
     *
     * @return void
     * @covers ::afterDeleteCommit()
     */
    public function testAfterDeleteCommit(): void
    {
        $getStream = function (): Stream {
            ($fh = fopen('php://memory', 'rb+')) || static::fail('Error opening temporary file');
            fwrite($fh, 'test file contents');
            rewind($fh);

            return new Stream($fh);
        };

        /** @var \Chialab\ObjectStorage\MultipartUploadInterface $storage */
        $storage = $this->Files->getContainer()->get(MultipartUploadInterface::class);

        // Test with finalized file
        $file = $this->Files->get('a975e08a-99a2-40f1-bb2d-e5c677b2cb8e');
        $storage->put(new FileObject($file->getStorageKey(), $getStream()))->wait();
        static::assertTrue($storage->has($file->getStorageKey())->wait());
        $this->Files->deleteOrFail($file);
        static::assertFalse($storage->has($file->getStorageKey())->wait());

        // Test with non-finalized file
        $file = $this->Files->get('97263b6c-e322-48d7-887c-e4261dd56069');
        $file->multipart_token = $storage->multipartInit(new FileObject($file->getStorageKey(), null))->wait();
        $storage->multipartUpload(
            new FileObject($file->getStorageKey(), $getStream()),
            $file->multipart_token,
            new FilePart(1, $getStream())
        )->wait();
        $this->Files->deleteOrFail($file);
        static::assertArrayNotHasKey($file->multipart_token, $storage->getMultipart()); // @phpstan-ignore-line
    }

    /**
     * Test {@see FilesTable::getFileUrl()} method.
     *
     * @return void
     * @covers ::getFileUrl()
     */
    public function testGetFileUrl(): void
    {
        $file = $this->Files->get('a975e08a-99a2-40f1-bb2d-e5c677b2cb8e');
        $expected = sprintf('https://static.example.com/%s/%s', $file->id, $file->filename);

        static::assertEquals($expected, $this->Files->getFileUrl($file));
    }

    /**
     * Provider for {@see FilesTableTest::testFinders()} tests.
     *
     * @return array[]
     */
    public function findersProvider(): array
    {
        return [
            'finalized files' => [
                'finalized',
                ['a975e08a-99a2-40f1-bb2d-e5c677b2cb8e', '2e760168-3a2a-4da4-b640-412c3bd793ec'],
            ],
            'not finalized files' => [
                'notFinalized',
                ['019d1a34-ab02-4ace-8d5d-306e3c081932', '97263b6c-e322-48d7-887c-e4261dd56069', '61324b6c-e322-48d7-887c-e4261dd56069'],
            ],
            'multipart files' => [
                'multipart',
                ['97263b6c-e322-48d7-887c-e4261dd56069', '61324b6c-e322-48d7-887c-e4261dd56069'],
            ],
        ];
    }

    /**
     * Test finder methods.
     *
     * @param string $finder
     * @param string[] $expected
     * @return void
     * @dataProvider findersProvider()
     * @covers ::findFinalized()
     * @covers ::findNotFinalized()
     * @covers ::findMultipart()
     */
    public function testFinders(string $finder, array $expected): void
    {
        $actual = $this->Files->find($finder)
            ->all()
            ->extract('id')
            ->toArray();

        static::assertEquals($expected, $actual);
    }
}
