<?php

namespace App\Exceptions;

class DoubleEvaluationException extends EvaluationException
{
    public function __construct(string $message = "Vous avez déjà soumis votre évaluation pour cette séance (RG15).")
    {
        parent::__construct($message);
    }
}
