<?php

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class DelaiAnnulationDepasseException extends ReservationException
{
    public function __construct(string $message = "L'annulation directe de la séance est impossible après son heure de début. Un litige doit être ouvert (RG10).")
    {
        parent::__construct($message, Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
