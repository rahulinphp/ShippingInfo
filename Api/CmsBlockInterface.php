<?php
namespace Hardwoods\ShippingInfo\Api;

interface CmsBlockInterface
{
    /**
     * Get CMS Block content by identifier
     *
     * @param string $blockIdentifier
     * @return string/null
     */
    public function getBlockContentByIdentifier(string $blockIdentifier): string|null;
}
