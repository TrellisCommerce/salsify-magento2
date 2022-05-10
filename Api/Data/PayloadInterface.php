<?php
/**
 * Copyright (c) Oleksii Solonenko, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace Trellis\Salsify\Api\Data;

interface PayloadInterface
{
    public const ENTITY_ID = 'entity_id';

    /**
     * @return void
     */
    public function setId($id);

    /**
     * @return string
     */
    public function getId();
}
