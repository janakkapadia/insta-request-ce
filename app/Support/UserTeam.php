<?php

namespace App\Support;

readonly class UserTeam
{
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public bool $isPersonal,
        public ?string $role,
        public ?string $roleLabel,
        public array $permissions = [],
        public ?bool $isCurrent = null,
    ) {
        //
    }
}
