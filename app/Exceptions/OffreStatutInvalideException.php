<?php

namespace App\Exceptions;

class OffreStatutInvalideException extends OffreException
{
    public function __construct(string $message = "Transition de statut d'offre invalide (RG04, RG17).")
    {
        parent::__construct($message);
    }
}
