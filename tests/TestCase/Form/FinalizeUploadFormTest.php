<?php
declare(strict_types=1);

namespace Chialab\CakeObjectStorage\Test\TestCase\Form;

use Cake\Core\Container;
use Cake\TestSuite\TestCase;
use Chialab\CakeObjectStorage\Form\FinalizeUploadForm;
use Chialab\CakeObjectStorage\Model\Entity\File;
use Chialab\ObjectStorage\FileObject;
use Chialab\ObjectStorage\FilePart;
use Chialab\ObjectStorage\InMemoryAdapter;
use Chialab\ObjectStorage\MultipartUploadInterface;
use GuzzleHttp\Psr7\Stream;
use Psr\Http\Message\StreamInterface;

/**
 * Tests for {@see \Chialab\CakeObjectStorage\Form\FinalizeUploadForm} class.
 *
 * @coversDefaultClass \Chialab\CakeObjectStorage\Form\FinalizeUploadForm
 */
class FinalizeUploadFormTest extends TestCase
{
    /**
     * @var string[]
     */
    protected $fixtures = ['plugin.Chialab/CakeObjectStorage.Files'];

    /**
     * Test subject
     */
    protected FinalizeUploadForm $FinalizeUpload;

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

        $this->FinalizeUpload = new FinalizeUploadForm();
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        unset($this->FinalizeUpload, $this->Storage);

        parent::tearDown();
    }

    /**
     * Test {@see FinalizeUploadForm::_execute()} method.
     *
     * @return void
     * @covers ::_execute()
     */
    public function testExecute(): void
    {
        $getStream = function (string $content = 'test file contents'): Stream {
            ($fh = fopen('php://memory', 'rb+')) || static::fail('Error opening temporary file');
            fwrite($fh, $content);
            rewind($fh);

            return new Stream($fh);
        };
        $id = '97263b6c-e322-48d7-887c-e4261dd56069';
        $contents = ['hello', 'world'];

        /** @var \Chialab\CakeObjectStorage\Model\Table\FilesTable $table */
        $table = $this->fetchTable('Chialab/CakeObjectStorage.Files');
        /** @var \Chialab\CakeObjectStorage\Model\Entity\File $file */
        $file = $table->get($id);
        $file->multipart_token = $this->Storage->multipartInit(new FileObject($file->getStorageKey(), null))->wait();
        $table->saveOrFail($file);
        $hashes = [];
        foreach ($contents as $index => $content) {
            $hashes[] = [
                'part' => $index + 1,
                'hash' => $this->Storage->multipartUpload(
                    new FileObject($file->getStorageKey(), $getStream($content)),
                    $file->multipart_token,
                    new FilePart($index + 1, $getStream($content))
                )->wait(),
            ];
        }

        static::assertTrue($file->is_multipart);
        static::assertFalse($file->is_finalized);
        static::assertArrayHasKey($file->multipart_token, $this->Storage->getMultipart()); // @phpstan-ignore-line

        $this->FinalizeUpload->execute(compact('id', 'hashes'));

        /** @var \Chialab\CakeObjectStorage\Model\Entity\File $file */
        $file = $table->get($id);

        static::assertTrue($file instanceof File);
        static::assertNull($file->is_multipart);
        static::assertTrue($file->is_finalized);
        static::assertNull($file->multipart_token);
        static::assertTrue($this->Storage->has($file->getStorageKey())->wait());

        /** @var \Chialab\ObjectStorage\FileObject $object */
        $object = $this->Storage->get($file->getStorageKey())->wait();

        static::assertInstanceOf(FileObject::class, $object);
        static::assertInstanceOf(StreamInterface::class, $object->data);
        $object->data->rewind();
        static::assertEquals(join('', $contents), $object->data->getContents());
    }
}
