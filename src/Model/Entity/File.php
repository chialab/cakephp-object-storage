<?php
declare(strict_types=1);

namespace Chialab\CakeObjectStorage\Model\Entity;

use Cake\ORM\Entity;
use Cake\ORM\Locator\LocatorAwareTrait;

/**
 * File Entity
 *
 * @property string $id
 * @property string $filename
 * @property string|null $mime_type
 * @property int $size
 * @property string|null $multipart_token
 * @property-read bool|null $is_multipart
 * @property-read bool $is_finalized
 * @property-read string|null $url
 * @property \Cake\I18n\FrozenTime $created
 * @property \Cake\I18n\FrozenTime|null $finalized
 * @property \Psr\Http\Message\StreamInterface|null $body
 */
class File extends Entity
{
    use LocatorAwareTrait;

    /**
     * Default minimum size for multipart upload chunks.
     *
     * @var int
     */
    public const DEFAULT_MULTIPART_CHUNK_MIN_SIZE = 2 << 20;

    /**
     * Default maximum size for multipart upload chunks.
     *
     * @var int
     */
    public const DEFAULT_MULTIPART_CHUNK_MAX_SIZE = 10 << 20;

    /**
     * @inheritDoc
     */
    protected $_accessible = [
        '*' => false,
        'filename' => true,
        'mime_type' => true,
        'size' => true,
    ];

    /**
     * @inheritDoc
     */
    protected $_virtual = [
        'is_multipart',
        'is_finalized',
        'url',
    ];

    /**
     * @inheritDoc
     */
    protected $_hidden = [
        'multipart_token',
    ];

    /**
     * Get minimum and maximum chunk sizes for multipart uploads.
     *
     * @return int[] Tuple with minimum and maximum. Either of them can be null.
     * @codeCoverageIgnore
     */
    public static function getMultipartChunkSize(): array
    {
        $toBytes = fn (string $size): int =>
        match (strtoupper(substr($size, -1))) {
            'G' => (int)$size << 30,
            'M' => (int)$size << 20,
            'K' => (int)$size << 10,
            default => (int)$size,
        };

        $postMaxSize = $toBytes((string)ini_get('post_max_size'));
        if ($postMaxSize <= 0) {
            $postMaxSize = PHP_INT_MAX;
        }

        return [min(static::DEFAULT_MULTIPART_CHUNK_MIN_SIZE, $postMaxSize), min(static::DEFAULT_MULTIPART_CHUNK_MAX_SIZE, $postMaxSize)];
    }

    /**
     * Get chunk size for multipart file upload.
     *
     * @return int Chunk size in bytes.
     * @codeCoverageIgnore
     */
    public static function getPreferredChunkSize(): int
    {
        [$min, $max] = static::getMultipartChunkSize();

        return $max ?: $min ?: static::DEFAULT_MULTIPART_CHUNK_MIN_SIZE;
    }

    /**
     * Getter for `is_finalized` virtual property.
     *
     * @return bool
     */
    protected function _getIsFinalized(): bool
    {
        return $this->finalized !== null;
    }

    /**
     * Return `true` if file is to be uploaded with multipart upload.
     *
     * @param bool|null $value Stored value.
     * @return bool|null
     */
    protected function _getIsMultipart(?bool $value): ?bool
    {
        if ($value !== null) {
            return $value;
        }
        if ($this->is_finalized) {
            return null;
        }

        return $this->multipart_token !== null;
    }

    /**
     * Getter for file URL.
     *
     * @param string|null $value Stored value.
     * @return string|null
     */
    protected function _getUrl(?string $value): ?string
    {
        if ($value !== null) {
            return $value;
        }

        /** @var \Chialab\CakeObjectStorage\Model\Table\FilesTable $table */
        $table = $this->fetchTable('Chialab/CakeObjectStorage.Files');

        return $table->getFileUrl($this);
    }

    /**
     * Get storage key where this content's data is stored.
     *
     * @return string
     */
    public function getStorageKey(): string
    {
        return sprintf('%s/%s', $this->id, $this->filename);
    }
}
