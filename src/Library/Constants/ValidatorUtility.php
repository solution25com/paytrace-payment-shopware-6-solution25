<?php

namespace solu1Paytrace\Library\Constants;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;

class ValidatorUtility
{
    private ValidatorInterface $validator;

    public function __construct(ValidatorInterface $validator)
    {
        $this->validator = $validator;
    }

    /**
     * Validate arbitrary data against one or more Symfony constraints.
     *
     * @param mixed                               $data
     * @param Constraint|list<Constraint>         $constraints
     * @return list<array{property: string, message: string}>
     */
    public function validateFields(mixed $data, Constraint|array $constraints): array
    {
        $violations = $this->validator->validate($data, $constraints);

        /** @var list<array{property: string, message: string}> $errors */
        $errors = [];

        if (\count($violations) > 0) {
            /** @var ConstraintViolationInterface $violation */
            foreach ($violations as $violation) {
                $errors[] = [
                    'property' => $violation->getPropertyPath(),
                    'message'  => $violation->getMessage(),
                ];
            }
        }

        return $errors;
    }
}
