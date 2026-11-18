<?php

namespace App\Exceptions;

class NoteInvalideException extends EvaluationException
{
    public function __construct(string $message = "La note doit être comprise entre 1 et 5 étoiles (RG15).")
    {
        parent::__construct($message);
    }
}
