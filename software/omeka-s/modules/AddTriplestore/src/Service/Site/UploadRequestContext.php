<?php

declare(strict_types=1);

namespace AddTriplestore\Service\Site;

use Laminas\Stdlib\Parameters;

/**
 * Normalized query/post parameters for upload routing (arrowhead / excavation).
 */
final class UploadRequestContext
{
    /** @var string|null */
    public $uploadType;

    /** @var string|null */
    public $itemSetId;

    /** @var string */
    public $mode;

    /**
     * Omeka item set id for numeric/API use (null if missing or not numeric).
     */
    public function getItemSetIdAsInt(): ?int
    {
        if ($this->itemSetId === null || $this->itemSetId === '') {
            return null;
        }

        if (!is_numeric($this->itemSetId)) {
            return null;
        }

        return (int) $this->itemSetId;
    }

    public static function fromRequestParameters(Parameters $query, Parameters $post): self
    {
        $o = new self();
        $uploadType = $query->get('upload_type') ?: $post->get('upload_type');
        $itemSetId = $query->get('item_set_id') ?: $post->get('item_set_id');
        $o->uploadType = $uploadType !== null && $uploadType !== '' ? (string) $uploadType : null;
        $o->itemSetId = $itemSetId !== null && $itemSetId !== '' ? (string) $itemSetId : null;
        $o->mode = (string) ($query->get('mode') ?: $post->get('mode') ?: 'upload');

        return $o;
    }
}
