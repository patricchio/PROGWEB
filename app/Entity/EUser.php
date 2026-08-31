<?php

declare(strict_types=1);

final class EUser
{
    public function __construct(
        public readonly int $id,
        public readonly string $username,
        public readonly string $email,
        public readonly string $passwordHash
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self((int) $row['id'], (string) $row['username'],
            (string) $row['email'], (string) $row['password_hash']);
    }
}
