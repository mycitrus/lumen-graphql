<?php namespace M1naret\GraphQL\Error;

class ValidationError extends Error
{
    protected $code = 422;

    public $validator;

    public function setValidator($validator)
    {
        $this->validator = $validator;

        return $this;
    }

    public function getValidatorMessages()
    {
        return $this->validator ? $this->validator->messages() : [];
    }
}
