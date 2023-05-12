<?php
declare(strict_types=1);

namespace Chialab\CakeObjectStorage\Test\TestCase\Form;

use Cake\Core\Container;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\TestSuite\TestCase;
use Chialab\CakeObjectStorage\Form\AbortForm;
use Chialab\CakeObjectStorage\Model\Entity\File;
use Chialab\ObjectStorage\FileObject;
use Chialab\ObjectStorage\FilePart;
use Chialab\ObjectStorage\InMemoryAdapter;
use Chialab\ObjectStorage\MultipartUploadInterface;
use GuzzleHttp\Psr7\Stream;

/**
 * Tests for {@see \Chialab\CakeObjectStorage\Form\AbortForm} class.
 *
 * @coversDefaultClass \Chialab\CakeObjectStorage\Form\AbortForm
 */
class AbortFormTest extends TestCase
{
    /**
     * @var string[]
     */
    protected $fixtures = ['plugin.Chialab/CakeObjectStorage.Files'];

    /**
     * Test subject
     */
    protected AbortForm $Abort;

    /**
     * Storage instance
     */
    protected InMemoryAdapter $Storage;

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
        $this->Storage = $container->get(MultipartUploadInterface::class);
        /** @var \Chialab\CakeObjectStorage\Model\Table\FilesTable $table */
        $table = $this->fetchTable('Chialab/CakeObjectStorage.Files');
        $table->setContainer($container);

        $this->Abort = new AbortForm();
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        unset($this->Abort, $this->Storage);

        parent::tearDown();
    }

    /**
     * Provider for {@see UploadFormTest::testExecute()} tests.
     *
     * @return array[]
     */
    public function executeProvider(): array
    {
        return [
            'multipart file' => [
                '97263b6c-e322-48d7-887c-e4261dd56069',
                null,
            ],
            'single part file' => [
                '019d1a34-ab02-4ace-8d5d-306e3c081932',
                RecordNotFoundException::class,
            ],
            'file not found' => [
                'zzyyxx',
                RecordNotFoundException::class,
            ],
            'file already finalized' => [
                'a975e08a-99a2-40f1-bb2d-e5c677b2cb8e',
                RecordNotFoundException::class,
            ],
        ];
    }

    /**
     * Test {@see AbortForm::_execute()} method.
     *
     * @param string $id
     * @param class-string<\Throwable>|null $expected
     * @return void
     * @dataProvider executeProvider()
     * @covers ::_execute()
     */
    public function testExecute(string $id, ?string $expected): void
    {
        $getStream = function (): Stream {
            ($fh = fopen('php://memory', 'rb+')) || static::fail('Error opening temporary file');
            fwrite($fh, 'test file contents');
            rewind($fh);

            return new Stream($fh);
        };

        /** @var \Chialab\CakeObjectStorage\Model\Table\FilesTable $table */
        $table = $this->fetchTable('Chialab/CakeObjectStorage.Files');

        if (is_string($expected)) {
            static::expectException($expected);
        } else {
            /** @var \Chialab\CakeObjectStorage\Model\Entity\File $file */
            $file = $table->get($id);
            $file->multipart_token = $this->Storage->multipartInit(new FileObject($file->getStorageKey(), null))->wait();
            $table->saveOrFail($file);
            $this->Storage->multipartUpload(
                new FileObject($file->getStorageKey(), $getStream()),
                $file->multipart_token,
                new FilePart(1, $getStream())
            )->wait();

            static::assertArrayHasKey($file->multipart_token, $this->Storage->getMultipart()); // @phpstan-ignore-line
        }

        $this->Abort->execute(compact('id'));

        /** @var \Chialab\CakeObjectStorage\Model\Entity\File $file */
        $file = $table->get($id);

        static::assertTrue($file instanceof File);
        static::assertTrue($file->is_multipart);
        static::assertFalse($file->is_finalized);
        static::assertIsString($file->multipart_token);
        static::assertFalse($this->Storage->has($file->getStorageKey())->wait());
        static::assertArrayNotHasKey($file->multipart_token, $this->Storage->getMultipart()); // @phpstan-ignore-line
    }
}
