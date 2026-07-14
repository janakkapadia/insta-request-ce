<?php

namespace App\Enums;

enum MergeStrategy: string
{
    case CreateNew = 'create_new';
    case MergeReplace = 'merge_replace';
    case MergeSkip = 'merge_skip';
    case Mirror = 'mirror';

    public function label(): string
    {
        return match ($this) {
            self::CreateNew => 'Create New Collection',
            self::MergeReplace => 'Merge & Replace Duplicates',
            self::MergeSkip => 'Merge & Skip Duplicates',
            self::Mirror => 'Mirror Spec (Sync & Prune)',
        };
    }
}
