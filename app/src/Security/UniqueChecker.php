<?php

declare(strict_types=1);

namespace App\Security;

use App\Service\Encrypter\EncrypterInterface;
use Cycle\ORM\ORMInterface;
use Spiral\Validator\AbstractChecker;

final class UniqueChecker extends AbstractChecker
{
    public const array MESSAGES = [
        'verify' => 'error_value_is_not_unique'
    ];

    public function __construct(
        private readonly ORMInterface $orm,
        private readonly EncrypterInterface $encrypter
    ) {
    }

    public function verify(mixed $value, string $role, string $field, array $withFields = [], array $exceptFields = [], bool $encrypted = false): bool
    {
        $values = $this->withValues($withFields);
        $values[$field] = $encrypted ? $this->encrypter->encrypt($value) : $value;

        $exceptValues = $this->withValues($exceptFields);

        if (empty($role)) {
            return false;
        }

        /** @var \Cycle\ORM\Select\Repository $repository */
        $repository = $this->orm->getRepository($role);

        $select = $repository->select();

        foreach ($values as $field => $value) {
            $select->where($field, $value);
        }

        foreach ($exceptValues as $field => $value) {
            $select->where($field, '!=', $value);
        }

        try {
            return $select->fetchOne() === null;
        } catch (\Throwable) {
            // If an error occurs, most likely related to found user, we assume the value is not unique.
            return false;
        }
    }

    private function withValues(array $fields): array
    {
        $values = [];

        foreach ($fields as $field) {
            if ($this->getValidator()->hasValue($field)) {
                $values[$field] = $this->getValidator()->getValue($field);
            }
        }

        return $values;
    }
}
