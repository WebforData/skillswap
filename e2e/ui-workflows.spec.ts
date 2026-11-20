import { test, expect, Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';

const suffix = Date.now();
const teacher = `teacher.ui.${suffix}@campus.test`;
const learner = `learner.ui.${suffix}@campus.test`;
const password = 'Password123!';
const title = `Python UI ${suffix}`;
async function login(page: Page, email: string, pass = password) {
    await page.goto('/connexion', { waitUntil: 'domcontentloaded' });
    await page.locator('#login-email').fill(email);
    await page.locator('#login-password').fill(pass);
    await page.getByRole('button', { name: 'Se connecter', exact: true }).click();
    await expect(page).toHaveURL(/\/(app|admin)$/);
}
async function register(page: Page, email: string, name: string) {
    await page.goto('/inscription', { waitUntil: 'domcontentloaded' });
    await page.locator('#register-nom').fill('Test');
    await page.locator('#register-prenom').fill(name);
    await page.locator('#register-email').fill(email);
    await page.locator('#register-password').fill(password);
    await page.locator('#register-password-confirm').fill(password);
    await page.getByRole('button', { name: 'Créer mon compte (+2 crédits)' }).click();
    await expect(page).toHaveURL('/app');
}

test('Student UI: registration, profile, live categories, slots, booking, credits, completion and ratings', async ({
    browser,
}) => {
    const a = await browser.newContext();
    const b = await browser.newContext();
    const teaching = await a.newPage();
    const learning = await b.newPage();
    teaching.on('dialog', (d) => d.accept());
    learning.on('dialog', (d) => d.accept());
    const errors: string[] = [];
    teaching.on('pageerror', (e) => errors.push(e.message));
    learning.on('pageerror', (e) => errors.push(e.message));
    await register(teaching, teacher, 'Teacher');
    await teaching.goto('/app/mes-offres', { waitUntil: 'domcontentloaded' });
    await teaching.getByRole('button', { name: 'Proposer une compétence' }).click();
    await teaching.locator('#offer-titre').fill(title);
    await teaching.locator('#offer-competence').fill('Python');
    await teaching
        .locator('#offer-categorie')
        .selectOption({ label: 'Informatique & Programmation' });
    await teaching
        .locator('#offer-desc')
        .fill('Une séance complète de Python pour tester le parcours étudiant.');
    await teaching.getByRole('button', { name: "Créer l'offre", exact: true }).click();
    await expect(teaching.getByRole('heading', { name: title })).toBeVisible();
    await teaching.getByRole('button', { name: 'Publier', exact: true }).click();
    await teaching
        .getByRole('button', { name: /Gérer les créneaux|Créneaux/ })
        .first()
        .click();
    const date = new Date();
    date.setDate(date.getDate() + 4);
    await teaching.locator('#slot-date').fill(date.toISOString().slice(0, 10));
    await teaching.locator('#slot-debut').fill('10:00');
    await teaching.locator('#slot-fin').fill('11:00');
    await teaching.getByLabel('Lien ou lieu privé exact (RG19)').fill('Salle Privée UI');
    await teaching.getByRole('button', { name: 'Enregistrer ce créneau' }).click();
    await expect(teaching.getByText('10:00', { exact: false }).last()).toBeVisible();
    await teaching
        .getByRole('button', { name: /Fermer/ })
        .last()
        .click();
    await register(learning, learner, 'Learner');
    await learning.goto('/app/profil', { waitUntil: 'domcontentloaded' });
    await learning.locator('#profile-competences_recherchees').fill('Python');
    await learning.getByRole('button', { name: 'Enregistrer le profil' }).click();
    await expect(learning.getByText('Profil enregistré.', { exact: true })).toBeVisible();
    await expect(learning.getByRole('cell', { name: 'bienvenue', exact: true })).toBeVisible();
    await learning.goto('/app', { waitUntil: 'domcontentloaded' });
    await expect(learning.getByRole('heading', { name: title })).toBeVisible();
    await learning.getByRole('link', { name: new RegExp(title) }).click();
    const offerUrl = learning.url();
    await expect(learning.getByText('Salle Privée UI', { exact: true })).toHaveCount(0);
    await learning.getByRole('button', { name: 'Réserver', exact: true }).click();
    await learning.getByRole('button', { name: 'Confirmer la demande', exact: true }).click();
    await teaching.goto('/app/reservations', { waitUntil: 'domcontentloaded' });
    await teaching.getByRole('button', { name: 'Accepter', exact: true }).click();
    await expect(teaching.getByText('Acceptée', { exact: true })).toBeVisible();
    await learning.goto('/app/planning', { waitUntil: 'domcontentloaded' });
    await expect(learning.getByText('Salle Privée UI', { exact: true })).toBeVisible();
    await learning.goto('/app/profil', { waitUntil: 'domcontentloaded' });
    await expect(learning.getByRole('cell', { name: 'blocage', exact: true })).toBeVisible();
    // Only the clock boundary is prepared outside the UI; confirmation and settlement use UI actions.
    execFileSync('php', ['tests/fixtures/advance-ui-session.php', teacher], {
        cwd: process.cwd(),
    });
    await teaching.goto('/app/reservations', { waitUntil: 'domcontentloaded' });
    await teaching.getByRole('button', { name: 'Confirmer la séance', exact: true }).click();
    await learning.goto('/app/reservations', { waitUntil: 'domcontentloaded' });
    await learning.getByRole('button', { name: /envoyées/i }).click();
    await learning.getByRole('button', { name: 'Confirmer la séance', exact: true }).click();
    await expect(learning.getByText(/Séance terminée et crédits transférés/)).toBeVisible();
    await learning.goto('/app/evaluations', { waitUntil: 'domcontentloaded' });
    await learning.locator('#review-session').selectOption({ index: 1 });
    await learning.locator('#review-comment').fill('Séance claire et utile.');
    await learning.getByRole('button', { name: 'Publier mon évaluation' }).click();
    await expect(
        learning.getByText('Votre évaluation a été enregistrée.', {
            exact: true,
        }),
    ).toBeVisible();
    await learning.goto(offerUrl, { waitUntil: 'domcontentloaded' });
    await learning.getByRole('link', { name: 'Consulter les avis', exact: true }).click();
    await expect(learning.getByText('Séance claire et utile.', { exact: true })).toBeVisible();
    await expect(learning.getByText(/moyenne 5/)).toBeVisible();
    await learning.goto('/app/notifications', {
        waitUntil: 'domcontentloaded',
    });
    await expect(learning.getByText(/Mise à jour de séance/).first()).toBeVisible();
    await learning.getByRole('button', { name: 'Marquer comme lue' }).first().click();
    await learning.reload({ waitUntil: 'domcontentloaded' });
    await expect(learning.getByText(/ · Lue/).first()).toBeVisible();
    await learning.setViewportSize({ width: 390, height: 844 });
    await learning.goto('/app/profil', { waitUntil: 'domcontentloaded' });
    await expect(
        learning.getByRole('heading', {
            name: 'Mon Profil & Portefeuille',
            exact: true,
        }),
    ).toBeVisible();
    await expect(learning.getByRole('cell', { name: 'transfert gain', exact: true })).toBeVisible();
    expect(
        await learning.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth),
    ).toBeTruthy();
    await learning.screenshot({
        path: '../outputs/skillswap-profile-mobile.png',
        fullPage: true,
    });
    expect(errors).toEqual([]);
    await a.close();
    await b.close();
});

test('Admin UI: dashboard, create/rename/delete category, users, moderation and disputes', async ({
    page,
}) => {
    page.on('dialog', (d) => d.accept());
    await login(page, 'admin@skillswap.edu', 'password');
    await expect(page.getByRole('heading', { name: /Indicateurs & Pilotage Campus/ })).toBeVisible();
    await page.screenshot({
        path: '../outputs/skillswap-admin-dashboard.png',
        fullPage: true,
    });
    for (const route of [
        '/admin/users',
        '/admin/categories',
        '/admin/offres',
        '/admin/litiges',
        '/admin/seances',
    ]) {
        await page.goto(route, { waitUntil: 'domcontentloaded' });
        await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
        await expect(page.getByText(/Impossible de charger|Erreur lors/)).toHaveCount(0);
    }
    await page.goto('/admin/categories', { waitUntil: 'domcontentloaded' });
    await page
        .getByRole('button', {
            name: /Nouvelle catégorie|Ajouter une catégorie/i,
        })
        .first()
        .click();
    await page.getByRole('dialog').getByRole('textbox').fill(`UI catégorie ${suffix}`);
    await page
        .getByRole('dialog')
        .getByRole('button', { name: /Créer|Enregistrer/ })
        .click();
    await expect(page.getByText(`UI catégorie ${suffix}`, { exact: true })).toBeVisible();
    await page
        .locator('tr')
        .filter({ hasText: `UI catégorie ${suffix}` })
        .getByRole('button', { name: 'Modifier', exact: true })
        .click();
    await page.getByRole('dialog').getByRole('textbox').fill(`UI renommée ${suffix}`);
    await page
        .getByRole('button', {
            name: 'Enregistrer les modifications',
            exact: true,
        })
        .click();
    await page
        .locator('tr')
        .filter({ hasText: `UI renommée ${suffix}` })
        .getByRole('button', { name: 'Supprimer', exact: true })
        .click();
    await page
        .getByRole('dialog')
        .getByRole('button', { name: /Supprimer/ })
        .click();
    await expect(page.getByText(`UI renommée ${suffix}`, { exact: true })).toHaveCount(0);
});

function fixture(scenario: 'empty' | 'pending' | 'accepted' | 'past' | 'moderation'): {
    title: string;
    offer: number;
    reservation: number;
    teacher: string;
    learner: string;
    other: string;
    learnerName: string;
} {
    return JSON.parse(
        execFileSync('php', ['tests/fixtures/prepare-ui-case.php', scenario], {
            cwd: process.cwd(),
            encoding: 'utf8',
        }),
    );
}

test('Student UI: refusal with reason, withdrawal, automatic rejection and cancellation', async ({
    browser,
}) => {
    const pending = fixture('pending');
    const a = await browser.newContext();
    const b = await browser.newContext();
    const teacherPage = await a.newPage();
    const learnerPage = await b.newPage();
    teacherPage.on('dialog', (d) => d.accept());
    learnerPage.on('dialog', (d) => d.accept());
    await login(teacherPage, pending.teacher);
    await teacherPage.goto('/app/reservations', {
        waitUntil: 'domcontentloaded',
    });
    await teacherPage
        .getByTestId(`reservation-${pending.reservation}`)
        .getByRole('button', { name: 'Refuser', exact: true })
        .click();
    await teacherPage
        .getByRole('dialog')
        .getByRole('textbox')
        .fill('Indisponibilité exceptionnelle.');
    await teacherPage.getByRole('button', { name: 'Confirmer le refus', exact: true }).click();
    await expect(
        teacherPage.getByText('Indisponibilité exceptionnelle.', {
            exact: false,
        }),
    ).toBeVisible();
    await login(learnerPage, pending.other);
    await learnerPage.goto('/app/reservations', {
        waitUntil: 'domcontentloaded',
    });
    await learnerPage.getByRole('button', { name: /envoyées/i }).click();
    await learnerPage.getByRole('button', { name: 'Retirer la demande', exact: true }).click();
    await expect(learnerPage.getByText('Annulée', { exact: true })).toBeVisible();
    await a.close();
    await b.close();
    const accepted = fixture('accepted');
    const c = await browser.newContext();
    const page = await c.newPage();
    page.on('dialog', (d) => d.accept());
    await login(page, accepted.learner);
    await page.goto('/app/reservations', { waitUntil: 'domcontentloaded' });
    await page.getByRole('button', { name: /envoyées/i }).click();
    await expect(
        page.getByRole('button', { name: 'Confirmer la séance', exact: true }),
    ).toHaveCount(0);
    await page.getByRole('button', { name: 'Annuler (RG10)', exact: true }).click();
    await page.getByRole('dialog').getByRole('textbox').fill('Annulation avant le début.');
    await page
        .getByRole('button', {
            name: /Confirmer l.annulation \(restitution des crédits\)/,
        })
        .click();
    await expect(page.getByText('Annulée', { exact: true })).toBeVisible();
    await page.goto('/app/profil', { waitUntil: 'domcontentloaded' });
    await expect(
        page.getByRole('cell', { name: 'deblocage annulation', exact: true }),
    ).toBeVisible();
    await c.close();
});

test('Student and admin UI: dispute evidence and definitive refund', async ({ browser }) => {
    const data = fixture('past');
    const context = await browser.newContext();
    const page = await context.newPage();
    page.on('dialog', (d) => d.accept());
    await login(page, data.learner);
    await page.goto('/app/reservations', { waitUntil: 'domcontentloaded' });
    await page.getByRole('button', { name: /envoyées/i }).click();
    await expect(page.getByRole('button', { name: 'Annuler (RG10)', exact: true })).toHaveCount(0);
    await page.getByRole('button', { name: 'Litige (RG12)', exact: true }).click();
    await page
        .getByRole('dialog')
        .getByRole('textbox')
        .fill('Le contenu de la séance ne correspondait pas à la description.');
    await page.getByRole('button', { name: 'Ouvrir le litige', exact: true }).click();
    await page.goto('/app/litiges', { waitUntil: 'domcontentloaded' });
    await expect(
        page.getByText('Le contenu de la séance ne correspondait pas à la description.', {
            exact: true,
        }),
    ).toBeVisible();
    await context.close();
    const t = await browser.newContext();
    const teacherPage = await t.newPage();
    await login(teacherPage, data.teacher);
    await teacherPage.goto('/app/litiges', { waitUntil: 'domcontentloaded' });
    await teacherPage
        .getByLabel('Votre témoignage')
        .fill('Voici mes éléments et le programme réellement suivi.');
    await teacherPage.getByRole('button', { name: 'Ajouter mon témoignage' }).click();
    await expect(
        teacherPage.getByText('Voici mes éléments et le programme réellement suivi.', {
            exact: true,
        }),
    ).toBeVisible();
    await t.close();
    const admin = await browser.newContext();
    const adminPage = await admin.newPage();
    await login(adminPage, 'admin@skillswap.edu', 'password');
    await adminPage.goto('/admin/litiges', { waitUntil: 'domcontentloaded' });
    const row = adminPage.locator('tr').filter({ hasText: data.learnerName });
    await row.getByRole('button', { name: /Consulter|Détail|Voir/i }).click();
    await expect(
        adminPage.getByText('Voici mes éléments et le programme réellement suivi.', {
            exact: true,
        }),
    ).toBeVisible();
    await adminPage.getByRole('button', { name: 'Fermer la boîte de dialogue' }).click();
    await row.getByRole('button', { name: /Arbitrer/ }).click();
    await adminPage
        .getByRole('dialog')
        .getByRole('textbox')
        .fill('Restitution après examen des éléments des deux participants.');
    await adminPage
        .getByRole('dialog')
        .getByRole('button', { name: /Exécuter l.arbitrage définitif/ })
        .click();
    await expect(
        adminPage
            .getByText(/Litige.*résolu|Arbitrage.*enregistré|Litige.*arbitré|Décision.*exécutée/i)
            .first(),
    ).toBeVisible();
    await admin.close();
});

test('Admin UI: deactivate a participant and release preserved credits through settlement', async ({
    page,
}) => {
    const data = fixture('accepted');
    page.on('dialog', (d) => d.accept());
    await login(page, 'admin@skillswap.edu', 'password');
    await page.goto('/admin/users', { waitUntil: 'domcontentloaded' });
    await page.getByRole('textbox').first().fill(data.learner);
    const user = page.locator('tr').filter({ hasText: data.learner });
    await user.getByRole('button', { name: /Désactiver/ }).click();
    await page
        .getByRole('dialog')
        .getByRole('button', { name: 'Confirmer la désactivation' })
        .click();
    await expect(page.getByText(/a été désactivé/).first()).toBeVisible();
    await page.goto('/admin/seances', { waitUntil: 'domcontentloaded' });
    const card = page.locator('form').filter({ has: page.locator(`#reason-${data.reservation}`) });
    await page
        .locator(`#reason-${data.reservation}`)
        .fill('Restitution après désactivation du compte étudiant.');
    await card.getByRole('button', { name: 'Régler définitivement' }).click();
    await expect(page.getByText('Séance réglée définitivement.', { exact: true })).toBeVisible();
});

test('Student UI: edit offer, retain private slot details, delete free slot and archive', async ({
    page,
}) => {
    const data = fixture('empty');
    page.on('dialog', (d) => d.accept());
    await login(page, data.teacher);
    await page.goto('/app/mes-offres', { waitUntil: 'domcontentloaded' });
    await page.getByRole('button', { name: 'Modifier', exact: true }).click();
    await page.locator('#offer-titre').fill(`${data.title} modifiée`);
    await page.getByRole('button', { name: 'Mettre à jour', exact: true }).click();
    await expect(
        page.getByRole('heading', {
            name: `${data.title} modifiée`,
            exact: true,
        }),
    ).toBeVisible();
    await page.getByRole('button', { name: 'Gérer les créneaux', exact: true }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Éditer', exact: true }).click();
    await expect(page.locator('#slot-prive')).not.toHaveValue('');
    const original = await page.locator('#slot-prive').inputValue();
    await page.locator('#slot-public').fill('Campus UI mis à jour');
    await page.getByRole('button', { name: 'Valider les modifications', exact: true }).click();
    await expect(page.getByText('Créneau mis à jour.', { exact: true })).toBeVisible();
    await page.getByRole('dialog').getByRole('button', { name: 'Éditer', exact: true }).click();
    await expect(page.locator('#slot-prive')).toHaveValue(original);
    await page.getByRole('dialog').getByRole('button', { name: 'Supprimer', exact: true }).click();
    await expect(page.getByText(/Aucun créneau pour le moment/)).toBeVisible();
    await page.getByRole('button', { name: 'Fermer la boîte de dialogue' }).click();
    await page.getByRole('button', { name: 'Archiver', exact: true }).click();
    await page.getByRole('button', { name: "Confirmer l'archivage", exact: true }).click();
    await expect(page.getByText('Archivée', { exact: true })).toBeVisible();
});

test('Admin UI: moderation cancels pending requests and preserves accepted sessions', async ({
    browser,
}) => {
    const data = fixture('moderation');
    const a = await browser.newContext();
    const admin = await a.newPage();
    await login(admin, 'admin@skillswap.edu', 'password');
    await admin.goto('/admin/offres', { waitUntil: 'domcontentloaded' });
    await admin.getByRole('textbox').first().fill(data.title);
    const row = admin.locator('tr').filter({ hasText: data.title });
    await row.getByRole('button', { name: /Archiver/ }).click();
    await admin
        .getByRole('dialog')
        .getByRole('button', { name: /Confirmer|Archiver/ })
        .click();
    await expect(admin.getByText(/archivée avec succès|a été archivée/i).first()).toBeVisible();
    await a.close();
    const b = await browser.newContext();
    const learnerPage = await b.newPage();
    await login(learnerPage, data.learner);
    await learnerPage.goto('/app/reservations', {
        waitUntil: 'domcontentloaded',
    });
    await learnerPage.getByRole('button', { name: /envoyées/i }).click();
    await expect(learnerPage.getByText('Acceptée', { exact: true })).toBeVisible();
    await b.close();
    const c = await browser.newContext();
    const otherPage = await c.newPage();
    await login(otherPage, data.other);
    await otherPage.goto('/app/reservations', {
        waitUntil: 'domcontentloaded',
    });
    await otherPage.getByRole('button', { name: /envoyées/i }).click();
    await expect(otherPage.getByText('Annulée', { exact: true })).toBeVisible();
    await c.close();
});

test('Public UI: combined catalogue filters, no results, sign-in error and logout', async ({
    page,
}) => {
    const data = fixture('empty');
    await page.goto('/catalogue', { waitUntil: 'domcontentloaded' });
    await page.locator('#search-input').fill(data.title);
    await page.locator('#filter-category').selectOption({ label: 'Informatique & Programmation' });
    await page.locator('#filter-mode').selectOption('presentiel');
    await page.locator('#filter-niveau').selectOption('debutant');
    await page.locator('#filter-duree').selectOption('1');
    const date = new Date();
    date.setDate(date.getDate() + 6);
    await page.locator('#filter-date').fill(date.toISOString().slice(0, 10));
    await page.getByRole('button', { name: 'Rechercher', exact: true }).click();
    await expect(page.getByRole('heading', { name: data.title, exact: true })).toBeVisible();
    await expect(page.getByRole('status')).toHaveCount(0);
    date.setDate(date.getDate() + 100);
    await page.locator('#filter-date').fill(date.toISOString().slice(0, 10));
    await expect(
        page.getByRole('heading', {
            name: 'Aucune offre trouvée',
            exact: true,
        }),
    ).toBeVisible();
    await page.goto('/connexion', { waitUntil: 'domcontentloaded' });
    await page.locator('#login-email').fill(data.learner);
    await page.locator('#login-password').fill('WrongPassword123!');
    await page.getByRole('button', { name: 'Se connecter', exact: true }).click();
    await expect(page.getByText(/incorrect|invalide|correspondent pas/i).first()).toBeVisible();
    await page.locator('#login-password').fill(password);
    await page.getByRole('button', { name: 'Se connecter', exact: true }).click();
    await expect(page).toHaveURL('/app');
    await page.getByRole('button', { name: new RegExp(data.learnerName) }).click();
    await page.getByRole('button', { name: 'Se déconnecter', exact: true }).click();
    await expect(
        page.getByRole('link', {
            name: 'Inscription (+2 crédits)',
            exact: true,
        }),
    ).toBeVisible();
    await page.goto('/app/profil', { waitUntil: 'domcontentloaded' });
    await expect(page).toHaveURL('/connexion');
});

test('Student UI: accepting one request automatically rejects the competing request', async ({
    browser,
}) => {
    const data = fixture('pending');
    const context = await browser.newContext();
    const page = await context.newPage();
    page.on('dialog', (d) => d.accept());
    await login(page, data.teacher);
    await page.goto('/app/reservations', { waitUntil: 'domcontentloaded' });
    await page
        .getByTestId(`reservation-${data.reservation}`)
        .getByRole('button', { name: 'Accepter', exact: true })
        .click();
    await expect(page.getByText('Acceptée', { exact: true })).toBeVisible();
    await expect(page.getByText('Refusée', { exact: true })).toBeVisible();
    await context.close();
    const other = await browser.newContext();
    const otherPage = await other.newPage();
    await login(otherPage, data.other);
    await otherPage.goto('/app/reservations', {
        waitUntil: 'domcontentloaded',
    });
    await otherPage.getByRole('button', { name: /envoyées/i }).click();
    await expect(otherPage.getByText('Refusée', { exact: true })).toBeVisible();
    await expect(otherPage.getByText(/Créneau déjà attribué/)).toBeVisible();
    await other.close();
});
