<?php

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class DecisionLitigeInvalideException extends LitigeException
{
    public function __construct(string $message = "La décision arbitrale choisie est invalide. Choix requis : 'transfert_intervenant' ou 'restitution_beneficiaire' (RG13).")
    {
        parent::__construct($message, Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
