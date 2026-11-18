<?php

namespace App\Exceptions;

class DureeOffreIncompatibleException extends OffreException
{
    public function __construct(string $message = "Impossible de modifier la durée de l'offre : des créneaux existants deviendraient incompatibles (RG02).")
    {
        parent::__construct($message);
    }
}
