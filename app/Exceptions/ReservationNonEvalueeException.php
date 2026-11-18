<?php

namespace App\Exceptions;

class ReservationNonEvalueeException extends EvaluationException
{
    public function __construct(string $message = "Une évaluation ne peut être déposée que pour une réservation réalisée (RG15).")
    {
        parent::__construct($message);
    }
}
