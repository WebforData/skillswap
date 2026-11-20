import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';

test.describe('SkillSwap Campus — Suite E2E Complète (10 Parcours)', () => {
    const timestamp = Date.now();
    const aliceEmail = `alice.e2e.${timestamp}@univ-paris.fr`;
    const loginTestEmail = `login.e2e.${timestamp}@univ-paris.fr`;
    const bobEmail = `bob.e2e.${timestamp}@univ-paris.fr`;
    const carolEmail = `carol.e2e.${timestamp}@univ-paris.fr`;
    const daveEmail = `dave.e2e.${timestamp}@univ-paris.fr`;
    const eveEmail = `eve.e2e.${timestamp}@univ-paris.fr`;
    const frankEmail = `frank.e2e.${timestamp}@univ-paris.fr`;
    const password = 'Password123!';

    let aliceToken = '';
    let bobToken = '';
    let adminToken = '';
    let catId = 1;
    let aliceOffreId = 0;
    let aliceSlotId = 0;
    let reservationId = 0;

    test.beforeAll(async ({ request }) => {
        // Authentifier l'administrateur
        const adminLoginRes = await request.post('/api/auth/login', {
            data: {
                email: 'admin@skillswap.edu',
                password: 'password',
            },
        });
        expect(adminLoginRes.status()).toBe(200);
        const adminData = await adminLoginRes.json();
        adminToken = adminData.token;

        // Récupérer une catégorie existante
        const catRes = await request.get('/api/admin/categories', {
            headers: { Authorization: `Bearer ${adminToken}` },
        });
        expect(catRes.status()).toBe(200);
        const catBody = await catRes.json();
        const categories = catBody.categories || catBody;
        expect(categories.length).toBeGreaterThan(0);
        catId = categories[0].id;
    });

    test('1. Inscription + Attribution des 2 crédits de bienvenue (RG01)', async ({ request }) => {
        const res = await request.post('/api/auth/register', {
            data: {
                nom: 'Alice',
                prenom: 'Etudiante',
                email: aliceEmail,
                password,
                password_confirmation: password,
                filiere: 'Informatique & Réseaux',
                role: 'etudiant',
            },
        });

        expect(res.status()).toBe(201);
        const body = await res.json();
        expect(body.user.email).toBe(aliceEmail);
        expect(body.user.role).toBe('etudiant');
        expect(body.user.statut).toBe('actif');
        expect(body.user.portefeuille.solde_disponible).toBe(2);
        expect(body.user.portefeuille.credits_bloques).toBe(0);
        expect(body.user.portefeuille.solde_total).toBe(2);
        aliceToken = body.token;
    });

    test('2. Connexion / Déconnexion et gestion du Bearer Token', async ({ request }) => {
        // Inscription de l'utilisateur dédié
        const regRes = await request.post('/api/auth/register', {
            data: {
                nom: 'Login',
                prenom: 'Tester',
                email: loginTestEmail,
                password,
                password_confirmation: password,
                filiere: 'Gestion',
            },
        });
        expect(regRes.status()).toBe(201);

        // Connexion
        const loginRes = await request.post('/api/auth/login', {
            data: {
                email: loginTestEmail,
                password,
            },
        });
        expect(loginRes.status()).toBe(200);
        const loginData = await loginRes.json();
        expect(loginData.token).toBeTruthy();
        expect(loginData.user.email).toBe(loginTestEmail);

        // Déconnexion
        const logoutRes = await request.post('/api/auth/logout', {
            headers: { Authorization: `Bearer ${loginData.token}` },
        });
        expect(logoutRes.status()).toBe(200);

        // Ancien token révoqué -> 401
        const meRes = await request.get('/api/auth/me', {
            headers: { Authorization: `Bearer ${loginData.token}` },
        });
        expect(meRes.status()).toBe(401);
    });

    test('3. Création et publication d\'une offre (RG04, RG17)', async ({ request }) => {
        // Création en brouillon
        const createRes = await request.post('/api/offres', {
            headers: { Authorization: `Bearer ${aliceToken}` },
            data: {
                titre: 'Cours de Python avancé et Algorithmique',
                competence: 'Python & Structures de données',
                description: 'Séance pratique sur les graphes et la programmation dynamique.',
                categorie_id: catId,
                niveau: 'intermediaire',
                duree: 1,
                mode: 'presentiel',
            },
        });
        expect(createRes.status()).toBe(201);
        const created = await createRes.json();
        aliceOffreId = created.offre.id;
        expect(created.offre.statut).toBe('brouillon');

        // Publication
        const pubRes = await request.post(`/api/offres/${aliceOffreId}/publier`, {
            headers: { Authorization: `Bearer ${aliceToken}` },
        });
        expect(pubRes.status()).toBe(200);
        const published = await pubRes.json();
        expect(published.offre.statut).toBe('publiee');

        // Vérification dans le catalogue public
        const catRes = await request.get('/api/offres');
        expect(catRes.status()).toBe(200);
        const catList = await catRes.json();
        const found = catList.data.some((o: any) => o.id === aliceOffreId);
        expect(found).toBe(true);
    });

    test('4. Création d\'un créneau horaire sur l\'offre (RG02)', async ({ request }) => {
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 2);
        const dateStr = tomorrow.toISOString().split('T')[0];

        const slotRes = await request.post(`/api/offres/${aliceOffreId}/creneaux`, {
            headers: { Authorization: `Bearer ${aliceToken}` },
            data: {
                date: dateStr,
                heure_debut: '14:00',
                heure_fin: '15:00',
                lieu_public: 'Bibliothèque Universitaire - Salle B2',
                lien_ou_lieu_prive: 'Code d\'accès porte: 4892 - Table 4',
            },
        });
        expect(slotRes.status()).toBe(201);
        const slotData = await slotRes.json();
        aliceSlotId = slotData.creneau.id;
        expect(aliceSlotId).toBeGreaterThan(0);
    });

    test('5. Demande de réservation sans blocage immédiat des crédits (RG05)', async ({ request }) => {
        // Inscription de Bob
        const regBob = await request.post('/api/auth/register', {
            data: {
                nom: 'Bob',
                prenom: 'Etudiant',
                email: bobEmail,
                password,
                password_confirmation: password,
                filiere: 'Mathématiques',
                role: 'etudiant',
            },
        });
        expect(regBob.status()).toBe(201);
        const bobBody = await regBob.json();
        bobToken = bobBody.token;

        // Bob vérifie son solde initial (2 crédits)
        expect(bobBody.user.portefeuille.solde_disponible).toBe(2);
        expect(bobBody.user.portefeuille.credits_bloques).toBe(0);

        // Bob fait une demande de réservation sur le créneau d'Alice
        const resBooking = await request.post('/api/reservations', {
            headers: { Authorization: `Bearer ${bobToken}` },
            data: {
                creneau_id: aliceSlotId,
            },
        });
        expect(resBooking.status()).toBe(201);
        const bookingData = await resBooking.json();
        reservationId = bookingData.reservation.id;
        expect(bookingData.reservation.statut).toBe('en_attente');

        // RG05 : Vérification stricte que le solde de Bob n'a PAS encore été bloqué
        const meBob = await request.get('/api/auth/me', {
            headers: { Authorization: `Bearer ${bobToken}` },
        });
        expect(meBob.status()).toBe(200);
        const meBobData = await meBob.json();
        expect(meBobData.user.portefeuille.solde_disponible).toBe(2);
        expect(meBobData.user.portefeuille.credits_bloques).toBe(0);
    });

    test('6. Acceptation avec blocage des crédits et révélation du lieu privé (RG06, RG07, RG19)', async ({ request }) => {
        // Alice accepte la réservation
        const acceptRes = await request.post(`/api/reservations/${reservationId}/accepter`, {
            headers: { Authorization: `Bearer ${aliceToken}` },
        });
        expect(acceptRes.status()).toBe(200);
        const acceptData = await acceptRes.json();
        expect(acceptData.reservation.statut).toBe('acceptee');

        // RG06 : Le solde de Bob est maintenant bloqué sous séquestre (1 dispo, 1 bloqué)
        const meBob = await request.get('/api/auth/me', {
            headers: { Authorization: `Bearer ${bobToken}` },
        });
        const meBobData = await meBob.json();
        expect(meBobData.user.portefeuille.solde_disponible).toBe(1);
        expect(meBobData.user.portefeuille.credits_bloques).toBe(1);

        // RG19 : Bob a maintenant accès au lieu privé du créneau
        const resDetail = await request.get(`/api/reservations/${reservationId}`, {
            headers: { Authorization: `Bearer ${bobToken}` },
        });
        expect(resDetail.status()).toBe(200);
        const resDetailData = await resDetail.json();
        expect(resDetailData.reservation.creneau.lieu_ou_lien_prive).toContain('4892');
    });

    test('7. Double confirmation et transfert des crédits post-séance (RG11)', async ({ request }) => {
        // Créer deux étudiants Carol (intervenante) et Dave (bénéficiaire)
        const regCarol = await request.post('/api/auth/register', {
            data: { nom: 'Carol', prenom: 'Prof', email: carolEmail, password, password_confirmation: password, filiere: 'Physique' },
        });
        const carolToken = (await regCarol.json()).token;

        const regDave = await request.post('/api/auth/register', {
            data: { nom: 'Dave', prenom: 'Eleve', email: daveEmail, password, password_confirmation: password, filiere: 'Chimie' },
        });
        const daveToken = (await regDave.json()).token;

        // Carol crée une offre conforme à OffreCreationRequest
        const offRes = await request.post('/api/offres', {
            headers: { Authorization: `Bearer ${carolToken}` },
            data: {
                titre: 'Cours de Thermodynamique appliquée',
                competence: 'Physique & Thermodynamique',
                description: 'Explication détaillée des principes de la thermodynamique et exercices.',
                categorie_id: catId,
                niveau: 'intermediaire',
                mode: 'presentiel',
                duree: 1,
            },
        });
        expect(offRes.status()).toBe(201);
        const offId = (await offRes.json()).offre.id;
        await request.post(`/api/offres/${offId}/publier`, { headers: { Authorization: `Bearer ${carolToken}` } });

        // Créneau futur pour la création (satisfait RG04 début futur)
        const futureDate = new Date();
        futureDate.setDate(futureDate.getDate() + 3);
        const futureStr = futureDate.toISOString().split('T')[0];

        const slotRes = await request.post(`/api/offres/${offId}/creneaux`, {
            headers: { Authorization: `Bearer ${carolToken}` },
            data: { date: futureStr, heure_debut: '10:00', heure_fin: '11:00', lieu_public: 'Salle 101', lien_ou_lieu_prive: 'Code porte: 1234' },
        });
        expect(slotRes.status()).toBe(201);
        const slotId = (await slotRes.json()).creneau.id;

        // Dave réserve
        const resBooking = await request.post('/api/reservations', {
            headers: { Authorization: `Bearer ${daveToken}` },
            data: { creneau_id: slotId },
        });
        expect(resBooking.status()).toBe(201);
        const resId = (await resBooking.json()).reservation.id;

        // Carol accepte (crédits bloqués Dave: 1)
        const acceptRes = await request.post(`/api/reservations/${resId}/accepter`, { headers: { Authorization: `Bearer ${carolToken}` } });
        expect(acceptRes.status()).toBe(200);

        // Simulation post-séance : mettre la date du créneau dans le passé pour satisfaire RG11 (now >= finSeance)
        execSync(`php scratch/set_past_creneau.php ${slotId}`);

        // Double confirmation RG11
        // 1. Carol confirme
        const c1 = await request.post(`/api/reservations/${resId}/confirmer`, { headers: { Authorization: `Bearer ${carolToken}` } });
        expect(c1.status()).toBe(200);

        // 2. Dave confirme
        const c2 = await request.post(`/api/reservations/${resId}/confirmer`, { headers: { Authorization: `Bearer ${daveToken}` } });
        expect(c2.status()).toBe(200);
        const c2Data = await c2.json();
        expect(c2Data.reservation.statut).toBe('realisee');

        // RG11 : Carol a reçu +1 crédit (solde 2 -> 3), Dave a vu ses crédits bloqués libérés (bloqués 1 -> 0)
        const meCarol = await request.get('/api/auth/me', { headers: { Authorization: `Bearer ${carolToken}` } });
        expect((await meCarol.json()).user.portefeuille.solde_disponible).toBe(3);

        const meDave = await request.get('/api/auth/me', { headers: { Authorization: `Bearer ${daveToken}` } });
        const meDaveData = await meDave.json();
        expect(meDaveData.user.portefeuille.solde_disponible).toBe(1);
        expect(meDaveData.user.portefeuille.credits_bloques).toBe(0);
    });

    test('8. Litige et arbitrage administratif avec restitution financière (RG12, RG13, RG14)', async ({ request }) => {
        // Créer Eve et Frank
        const regEve = await request.post('/api/auth/register', {
            data: { nom: 'Eve', prenom: 'Prof', email: eveEmail, password, password_confirmation: password, filiere: 'Droit' },
        });
        const eveToken = (await regEve.json()).token;

        const regFrank = await request.post('/api/auth/register', {
            data: { nom: 'Frank', prenom: 'Eleve', email: frankEmail, password, password_confirmation: password, filiere: 'Droit' },
        });
        const frankToken = (await regFrank.json()).token;

        // Eve crée offre et créneau
        const offRes = await request.post('/api/offres', {
            headers: { Authorization: `Bearer ${eveToken}` },
            data: {
                titre: 'Introduction générale au Droit Civil',
                competence: 'Droit Civil & Méthodologie',
                description: 'Séance sur les sources du droit et la méthode du cas pratique.',
                categorie_id: catId,
                niveau: 'debutant',
                mode: 'distanciel',
                duree: 1,
            },
        });
        expect(offRes.status()).toBe(201);
        const offId = (await offRes.json()).offre.id;
        await request.post(`/api/offres/${offId}/publier`, { headers: { Authorization: `Bearer ${eveToken}` } });

        const nextWeek = new Date();
        nextWeek.setDate(nextWeek.getDate() + 5);
        const nextWeekStr = nextWeek.toISOString().split('T')[0];

        const slotRes = await request.post(`/api/offres/${offId}/creneaux`, {
            headers: { Authorization: `Bearer ${eveToken}` },
            data: { date: nextWeekStr, heure_debut: '16:00', heure_fin: '17:00', lieu_public: 'Visioconférence Google Meet', lien_ou_lieu_prive: 'https://meet.google.com/abc-defg-hij' },
        });
        expect(slotRes.status()).toBe(201);
        const slotId = (await slotRes.json()).creneau.id;

        // Frank réserve et Eve accepte
        const resBooking = await request.post('/api/reservations', {
            headers: { Authorization: `Bearer ${frankToken}` },
            data: { creneau_id: slotId },
        });
        expect(resBooking.status()).toBe(201);
        const resId = (await resBooking.json()).reservation.id;
        const acceptRes = await request.post(`/api/reservations/${resId}/accepter`, { headers: { Authorization: `Bearer ${eveToken}` } });
        expect(acceptRes.status()).toBe(200);

        // Simulation début de séance : mettre la date dans le passé pour satisfaire RG12 (début séance atteint)
        execSync(`php scratch/set_past_creneau.php ${slotId}`);

        // RG12 : Frank ouvre un litige car l'intervenant était absent
        const disputeRes = await request.post(`/api/reservations/${resId}/litige`, {
            headers: { Authorization: `Bearer ${frankToken}` },
            data: { description: 'L\'intervenant ne s\'est pas présenté à la séance convenue.' },
        });
        expect(disputeRes.status()).toBe(201);
        const disputeData = await disputeRes.json();
        const disputeId = disputeData.litige.id;

        // RG13, RG14 : L'administrateur arbitre et tranche en faveur du remboursement intégral
        const arbitrateRes = await request.post(`/api/litiges/${disputeId}/arbitrer`, {
            headers: { Authorization: `Bearer ${adminToken}` },
            data: {
                decision: 'restitution_beneficiaire',
                motif: 'Absence non justifiée constatée de l\'intervenant. Remboursement accordé.',
            },
        });
        expect(arbitrateRes.status()).toBe(200);
        const arbitrateData = await arbitrateRes.json();
        expect(arbitrateData.litige.statut).toBe('resolu');

        // RG13 : Frank retrouve son solde de 2 crédits disponibles, 0 bloqués
        const meFrank = await request.get('/api/auth/me', { headers: { Authorization: `Bearer ${frankToken}` } });
        const meFrankData = await meFrank.json();
        expect(meFrankData.user.portefeuille.solde_disponible).toBe(2);
        expect(meFrankData.user.portefeuille.credits_bloques).toBe(0);
    });

    test('9. Refus d\'accès administrateur pour un compte étudiant', async ({ request, page }) => {
        // Enregistrer un étudiant dédié
        const regStudent = await request.post('/api/auth/register', {
            data: {
                nom: 'Normal',
                prenom: 'Student',
                email: `student.isolated.${timestamp}@univ-paris.fr`,
                password,
                password_confirmation: password,
                filiere: 'Lettres',
            },
        });
        expect(regStudent.status()).toBe(201);
        const studentToken = (await regStudent.json()).token;

        // Requête API directe avec token étudiant -> 403 Forbidden
        const adminUsersRes = await request.get('/api/admin/users', {
            headers: { Authorization: `Bearer ${studentToken}` },
        });
        expect(adminUsersRes.status()).toBe(403);

        const adminStatsRes = await request.get('/api/admin/stats', {
            headers: { Authorization: `Bearer ${studentToken}` },
        });
        expect(adminStatsRes.status()).toBe(403);

        // Navigation web sur /connexion pour vérifier le rendu du formulaire
        await page.goto('/connexion');
        await expect(page.locator('input[type="email"]')).toBeVisible();
    });

    test('10. Compte désactivé interdit d\'accès API avec son ancien token (RG18)', async ({ request }) => {
        // 1. Enregistrer un étudiant dédié pour la désactivation
        const regTarget = await request.post('/api/auth/register', {
            data: {
                nom: 'Desactive',
                prenom: 'User',
                email: `desactive.target.${timestamp}@univ-paris.fr`,
                password,
                password_confirmation: password,
                filiere: 'Biologie',
            },
        });
        expect(regTarget.status()).toBe(201);
        const targetData = await regTarget.json();
        const targetId = targetData.user.id;
        const targetToken = targetData.token;

        // 2. L'administrateur désactive le compte
        const deactRes = await request.patch(`/api/admin/users/${targetId}/desactiver`, {
            headers: { Authorization: `Bearer ${adminToken}` },
        });
        expect(deactRes.status()).toBe(200);

        // 3. L'étudiant désactivé tente d'appeler /api/auth/me avec son token -> 403 CompteDesactiveException
        const meBlocked = await request.get('/api/auth/me', {
            headers: { Authorization: `Bearer ${targetToken}` },
        });
        expect(meBlocked.status()).toBe(403);
        const meBlockedData = await meBlocked.json();
        expect(meBlockedData.error).toBe('CompteDesactiveException');
        expect(meBlockedData.message).toContain('désactivé');

        // 4. L'étudiant désactivé tente d'accéder au planning -> 403
        const planningBlocked = await request.get('/api/planning', {
            headers: { Authorization: `Bearer ${targetToken}` },
        });
        expect(planningBlocked.status()).toBe(403);

        // 5. L'étudiant désactivé tente de se reconnecter -> 403 Forbidden
        const loginBlocked = await request.post('/api/auth/login', {
            data: { email: `desactive.target.${timestamp}@univ-paris.fr`, password },
        });
        expect(loginBlocked.status()).toBe(403);
    });

    test('11. Navigation UI Frontend (React 19 SPA & Layouts)', async ({ page }) => {
        // Navigation vers la page d'accueil
        await page.goto('/');
        await expect(page).toHaveTitle(/SkillSwap Campus/);
        await expect(page.locator('text=1 heure donnée = 1 heure reçue')).toBeVisible();

        // Navigation vers le catalogue
        await page.goto('/catalogue');
        await expect(page.locator('text=Explorer les cours et compétences')).toBeVisible();

        // Navigation vers la page de connexion
        await page.goto('/connexion');
        await expect(page.locator('input[type="email"]')).toBeVisible();
        await expect(page.locator('input[type="password"]')).toBeVisible();
    });
});
