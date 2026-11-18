<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // -------------------------------------------------------------
        // 1. RG02 : Concordance durée Créneau / Offre (INSERTION)
        // -------------------------------------------------------------
        DB::unprepared("
            CREATE TRIGGER trg_check_duree_creneau_before_insert
            BEFORE INSERT ON creneaux
            FOR EACH ROW
            BEGIN
                DECLARE v_duree_offre INT;
                SELECT duree INTO v_duree_offre FROM offres WHERE id = NEW.offre_id;
                
                IF TIMESTAMPDIFF(MINUTE, NEW.heure_debut, NEW.heure_fin) != (v_duree_offre * 60) THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'La duree du creneau ne correspond pas a la duree specifiee dans l offre (RG02)';
                END IF;
            END;
        ");

        // -------------------------------------------------------------
        // 2. CORRECTION 2 (RG02) : Concordance durée Créneau / Offre (MISE À JOUR)
        // -------------------------------------------------------------
        DB::unprepared("
            CREATE TRIGGER trg_check_duree_creneau_before_update
            BEFORE UPDATE ON creneaux
            FOR EACH ROW
            BEGIN
                DECLARE v_duree_offre INT;
                SELECT duree INTO v_duree_offre FROM offres WHERE id = NEW.offre_id;
                
                IF TIMESTAMPDIFF(MINUTE, NEW.heure_debut, NEW.heure_fin) != (v_duree_offre * 60) THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'La duree modifiee du creneau ne correspond pas a la duree de l offre parente (RG02)';
                END IF;
            END;
        ");

        // -------------------------------------------------------------
        // 3. CORRECTION 3 (RG02, RG17) : Protection de la durée de l'Offre
        // -------------------------------------------------------------
        DB::unprepared("
            CREATE TRIGGER trg_check_offre_duree_before_update
            BEFORE UPDATE ON offres
            FOR EACH ROW
            BEGIN
                IF NEW.duree != OLD.duree THEN
                    IF EXISTS (
                        SELECT 1 FROM creneaux 
                        WHERE offre_id = NEW.id 
                        AND TIMESTAMPDIFF(MINUTE, heure_debut, heure_fin) != (NEW.duree * 60)
                    ) THEN
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'Impossible de modifier la duree de l offre : des creneaux existants deviendraient incompatibles (RG02)';
                    END IF;
                END IF;
            END;
        ");

        // -------------------------------------------------------------
        // 4. RG03 : Interdiction de l'auto-réservation
        // -------------------------------------------------------------
        DB::unprepared("
            CREATE TRIGGER trg_check_auto_reservation_before_insert
            BEFORE INSERT ON reservations
            FOR EACH ROW
            BEGIN
                DECLARE v_intervenant_id BIGINT UNSIGNED;
                SELECT o.etudiant_id INTO v_intervenant_id 
                FROM creneaux c
                JOIN offres o ON c.offre_id = o.id
                WHERE c.id = NEW.creneau_id;

                IF NEW.beneficiaire_id = v_intervenant_id THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Un etudiant ne peut pas reserver sa propre offre (RG03)';
                END IF;
            END;
        ");

        // -------------------------------------------------------------
        // 5. CORRECTION 5 (RG11) : Vérification stricte du statut 'realisee'
        // -------------------------------------------------------------
        DB::unprepared("
            CREATE TRIGGER trg_check_reservation_realisee_before_insert
            BEFORE INSERT ON reservations
            FOR EACH ROW
            BEGIN
                IF NEW.statut = 'realisee' AND (NEW.confirmation_intervenant = FALSE OR NEW.confirmation_beneficiaire = FALSE) THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Le statut realisee exige obligatoirement la double confirmation des participants (RG11)';
                END IF;
            END;
        ");

        DB::unprepared("
            CREATE TRIGGER trg_check_reservation_realisee_before_update
            BEFORE UPDATE ON reservations
            FOR EACH ROW
            BEGIN
                IF NEW.statut = 'realisee' AND (NEW.confirmation_intervenant = FALSE OR NEW.confirmation_beneficiaire = FALSE) THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Le passage au statut realisee exige obligatoirement la confirmation des deux participants (RG11)';
                END IF;
            END;
        ");

        // -------------------------------------------------------------
        // 6. RG15 : Validité de l'Évaluation lors de l'insertion
        // -------------------------------------------------------------
        DB::unprepared("
            CREATE TRIGGER trg_check_evaluation_validite_before_insert
            BEFORE INSERT ON evaluations
            FOR EACH ROW
            BEGIN
                DECLARE v_statut VARCHAR(20);
                DECLARE v_beneficiaire_id BIGINT UNSIGNED;
                DECLARE v_intervenant_id BIGINT UNSIGNED;

                SELECT r.statut, r.beneficiaire_id, o.etudiant_id 
                INTO v_statut, v_beneficiaire_id, v_intervenant_id
                FROM reservations r
                JOIN creneaux c ON r.creneau_id = c.id
                JOIN offres o ON c.offre_id = o.id
                WHERE r.id = NEW.reservation_id;

                IF v_statut != 'realisee' THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Une evaluation ne peut etre deposee que pour une reservation realisee (RG15)';
                END IF;

                IF NOT (
                    (NEW.auteur_id = v_beneficiaire_id AND NEW.cible_id = v_intervenant_id) OR
                    (NEW.auteur_id = v_intervenant_id AND NEW.cible_id = v_beneficiaire_id)
                ) THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'L evaluation ne peut concerner que les deux participants legitimes de la seance (RG15)';
                END IF;
            END;
        ");

        // -------------------------------------------------------------
        // 7. CORRECTION 4 (RG15) : Immuabilité des participants & contrôle lors de l'UPDATE
        // -------------------------------------------------------------
        DB::unprepared("
            CREATE TRIGGER trg_check_evaluation_validite_before_update
            BEFORE UPDATE ON evaluations
            FOR EACH ROW
            BEGIN
                DECLARE v_statut VARCHAR(20);

                -- Interdiction formelle de changer les acteurs ou la réservation associée
                IF NEW.auteur_id != OLD.auteur_id OR NEW.cible_id != OLD.cible_id OR NEW.reservation_id != OLD.reservation_id THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Modification frauduleuse interdite : les participants et la reservation d une evaluation sont immuables (RG15)';
                END IF;

                SELECT statut INTO v_statut FROM reservations WHERE id = NEW.reservation_id;
                IF v_statut != 'realisee' THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Une evaluation ne peut etre modifiee que si la reservation associee demeure realisee (RG15)';
                END IF;
            END;
        ");
    }

    public function down(): void
    {
        DB::unprepared("DROP TRIGGER IF EXISTS trg_check_duree_creneau_before_insert;");
        DB::unprepared("DROP TRIGGER IF EXISTS trg_check_duree_creneau_before_update;");
        DB::unprepared("DROP TRIGGER IF EXISTS trg_check_offre_duree_before_update;");
        DB::unprepared("DROP TRIGGER IF EXISTS trg_check_auto_reservation_before_insert;");
        DB::unprepared("DROP TRIGGER IF EXISTS trg_check_reservation_realisee_before_insert;");
        DB::unprepared("DROP TRIGGER IF EXISTS trg_check_reservation_realisee_before_update;");
        DB::unprepared("DROP TRIGGER IF EXISTS trg_check_evaluation_validite_before_insert;");
        DB::unprepared("DROP TRIGGER IF EXISTS trg_check_evaluation_validite_before_update;");
    }
};

