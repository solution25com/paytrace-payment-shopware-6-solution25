<?php

namespace PayTrace\Library\Constants;

use Symfony\Component\Validator\Validator\ValidatorInterface;

class ValidatorUtility
{
  private ValidatorInterface $validator;
  public function __construct(ValidatorInterface $validator)
  {
    $this->validator = $validator;
  }
  public function validateFields($data, $constraints): array
  {
    $violations = $this->validator->validate($data, $constraints);
    $errors     = [];
    if (count($violations) > 0) {
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