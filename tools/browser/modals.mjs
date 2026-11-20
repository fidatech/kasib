// Opens the live demo in Chrome and checks that forms open in a modal, come back with their mistakes,
// and save. Screenshots are written to ./shots for a person to look at.
import { chromium } from 'playwright';
import fs from 'node:fs';

const site = process.env.SITE || 'https://demo.kasib.af';
fs.mkdirSync('shots', { recursive: true });
const results = [];
const check = (name, ok, more = '') => { results.push({ name, ok, more }); console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${more ? '  (' + more + ')' : ''}`); };

const browser = await chromium.launch({ channel: 'chrome' });
for (const [label, viewport, locale] of [['desktop', { width: 1440, height: 900 }, 'en'], ['phone', { width: 390, height: 844 }, 'fa']]) {
    const page = await browser.newPage({ viewport });
    const errors = [];
    page.on('pageerror', (e) => errors.push(e.message));
    await page.goto(site + '/login');
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
    // the guide of the screen starts by itself in the demo: it is put away
    await page.evaluate(() => { try { localStorage.setItem('hb.tour.' + window.ERP_TOUR.user, '1'); } catch (e) {} });
    const setLocale = (l) => page.evaluate(async (l) => {
            const t = document.querySelector('meta[name="csrf-token"]')?.content;
            await fetch('/language', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: '_token=' + encodeURIComponent(t) + '&locale=' + l, credentials: 'same-origin' });
        }, l);
    if (locale !== 'en') {
        await page.evaluate(async (l) => {
            const t = document.querySelector('meta[name="csrf-token"]')?.content || document.querySelector('input[name="_token"]')?.value;
            await fetch('/language', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: '_token=' + encodeURIComponent(t) + '&locale=' + l, credentials: 'same-origin' });
        }, locale);
    }
    await page.goto(site + '/');
    await page.waitForLoadState('networkidle');
    await page.keyboard.press('Escape');
    await page.screenshot({ path: `shots/${label}-1-dashboard.png`, fullPage: true });

    // a customer: the list, then "Add" opens the form over it
    await page.goto(site + '/accounts/customers');
    await page.waitForLoadState('networkidle');
    await page.keyboard.press('Escape');
    await page.screenshot({ path: `shots/${label}-2-customers.png` });
    const add = page.locator('.app-content a[href$="/create"]').first();
    // the modal is there at once, before the form has arrived
    let t0 = Date.now();
    await add.click();
    const modal = page.locator('.erp-form-modal.show');
    await modal.waitFor({ timeout: 15000 }).catch(() => {});
    const opened = Date.now() - t0;
    check(`${label}: the modal is on the screen within 300 ms of the click`, opened < 300, opened + ' ms');
    await page.screenshot({ path: `shots/${label}-3a-opening.png` });
    await modal.locator('.erp-form-in').waitFor({ timeout: 15000 }).catch(() => {});
    check(`${label}: the form arrives in it`, (await modal.locator('.erp-form-in form').count()) > 0, (Date.now() - t0) + ' ms');
    check(`${label}: the page under it was not loaded anew`, await page.evaluate(() => !document.body.classList.contains('is-loading')));
    check(`${label}: Add opens a modal`, await modal.isVisible(), page.url());
    check(`${label}: the address stays on the list`, !/\/create$/.test(page.url()), page.url());
    await page.waitForTimeout(400);
    await page.screenshot({ path: `shots/${label}-3-add-customer.png` });

    // sent empty: the form comes back in the modal with what is missing marked. The browser's own check of
    // required boxes is switched off for this, so it is the server that answers.
    await modal.locator('form').first().evaluate((f) => { f.noValidate = true; });
    await modal.locator('button[type="submit"]').last().click();
    await page.waitForTimeout(2500);
    check(`${label}: a mistake keeps the modal open`, await modal.isVisible());
    check(`${label}: the mistake is marked`, (await modal.locator('.is-invalid').count()) > 0);
    await page.screenshot({ path: `shots/${label}-4-add-customer-errors.png` });

    // filled in: saved, the modal closes and the list shows the new customer
    const name = 'Browser check ' + label + ' ' + Date.now().toString().slice(-5);
    await modal.locator('input[name="name"]').fill(name);
    await modal.locator('button[type="submit"]').last().click();
    await page.waitForTimeout(3000);
    check(`${label}: saved, the modal closes`, !(await modal.isVisible()));
    const listed = await page.locator('.app-content').innerText();
    check(`${label}: the new customer is on the page`, listed.includes(name));
    await page.screenshot({ path: `shots/${label}-5-after-save.png` });

    // the pencil of a row opens its form the same way, and what was typed is not lost by a slip
    const pencil = page.locator('.app-content a[href$="/edit"]').first();
    await pencil.click();
    await modal.locator('.erp-form-in').waitFor({ timeout: 15000 }).catch(() => {});
    check(`${label}: Edit opens in the modal with its record`, (await modal.locator('input[name="name"]').inputValue().catch(() => '')) !== '');
    await page.screenshot({ path: `shots/${label}-5a-edit.png` });
    await modal.locator('input[name="phone"]').fill('0700 000 001');
    await page.keyboard.press('Escape');
    await page.waitForTimeout(500);
    check(`${label}: closing a changed form asks first`, (await page.locator('.erp-confirm.show').count()) === 1 && await modal.isVisible());
    await page.screenshot({ path: `shots/${label}-5a2-discard.png` });
    await page.locator('.erp-confirm.show [data-ok]').click();
    await page.waitForTimeout(600);
    check(`${label}: and then closes`, !(await modal.isVisible()));

    // a paper saved from the dashboard: one stays on the dashboard
    await page.goto(site + '/');
    await page.waitForLoadState('networkidle');
    const quick = page.locator('.quickbar a[href$="/money-in/create"]').first();
    if (await quick.count() && await quick.isVisible()) {
        await quick.click();
        await modal.locator('.erp-form-in').waitFor({ timeout: 15000 }).catch(() => {});
        check(`${label}: the quick bar opens Money In in the modal`, await modal.isVisible() && new URL(page.url()).pathname === '/');
        await modal.locator('select[name="party_id"]').selectOption({ index: 1 }, { force: true }).catch(() => {});
        await modal.locator('input[name="amount"]').fill('1250');
        await page.screenshot({ path: `shots/${label}-5a3-money-in-filled.png` });
check(`${label}: the form carries no frame of its own`, await page.evaluate(() => { const f = document.querySelector('.erp-form-in > form'); return !!f && parseFloat(getComputedStyle(f).borderLeftWidth) === 0; }));
        await modal.locator('button[type="submit"]').last().click();
        await page.waitForTimeout(3500);
        check(`${label}: saved from the dashboard, one stays on the dashboard`, !(await modal.isVisible()) && new URL(page.url()).pathname === '/', page.url());
        await page.screenshot({ path: `shots/${label}-5a4-after-money-in.png` });
    }
    await page.goto(site + '/accounts/customers');
    await page.waitForLoadState('networkidle');

    // the notes of an account: a list with its own form, which stays open after a note is added
    const notes = page.locator('.app-content a[data-modal]', { hasText: /Notes|یادداشت|یادښت/ }).first();
    if (await notes.count()) {
        await notes.click();
        await page.waitForTimeout(2500);
        check(`${label}: Notes open in a modal`, await modal.isVisible());
        await page.screenshot({ path: `shots/${label}-5b-notes.png` });
        await page.keyboard.press('Escape');
        await page.waitForTimeout(400);
    }
    // the profile, from the menu of the user
    await page.evaluate(() => window.ERP && window.ERP.openForm('/profile'));
    await page.waitForTimeout(2500);
    check(`${label}: the profile opens in a modal`, await modal.isVisible());
    await page.screenshot({ path: `shots/${label}-5c-profile.png` });
    await page.keyboard.press('Escape');
    await page.waitForTimeout(400);

    // a paper: money in, and a sale with its lines in a wide modal
    for (const [step, path] of [['6-money-in', '/money-in/create'], ['7-sale', '/trade/sales/create'], ['8-purchase', '/trade/purchases/create']]) {
        await page.goto(site + '/');
        await page.waitForLoadState('networkidle');
        await page.evaluate((p) => window.ERP && window.ERP.openForm(p), path);
        await page.waitForTimeout(2500);
        check(`${label}: ${path} opens in a modal`, await page.locator('.erp-form-modal.show').isVisible());
        await page.screenshot({ path: `shots/${label}-${step}.png` });
        await page.keyboard.press('Escape');
        await page.waitForTimeout(400);
    }
    check(`${label}: no script errors`, errors.length === 0, errors.slice(0, 3).join(' | '));
    // the budget form: its list of categories opens whole above the modal, and a choice is taken
    await page.goto(site + '/budgets');
    await page.waitForLoadState('networkidle');
    await page.evaluate(() => window.ERP && window.ERP.openForm('/budgets/create'));
    await modal.locator('.erp-form-in').waitFor({ timeout: 15000 }).catch(() => {});
    await page.waitForTimeout(400);
    const pickBox = modal.locator('.ss > input.form-control').first();
    await pickBox.click();
    await page.waitForTimeout(400);
    const box = await modal.locator('.ss.open .ss-list').boundingBox().catch(() => null);
    const vh = (page.viewportSize() || { height: 800 }).height;
    check(`${label}: the budget's category list opens`, !!box && box.height > 100, box ? Math.round(box.height) + ' px high' : 'not open');
    check(`${label}: the list lies inside the screen`, !!box && box.y >= 0 && box.y + box.height <= vh);
    await page.screenshot({ path: `shots/${label}-9-budget-list.png` });
    await modal.locator('.ss.open .ss-item').nth(2).click().catch(() => {});
    await page.waitForTimeout(300);
    check(`${label}: a category is chosen from the list`, (await modal.locator('select[name="account_category_id"]').inputValue().catch(() => '')) !== '');
    await page.screenshot({ path: `shots/${label}-9b-budget-chosen.png` });
    await page.keyboard.press('Escape');
    await page.waitForTimeout(300);
    if (await page.locator('.erp-confirm.show').count()) { await page.locator('.erp-confirm.show [data-ok]').click(); await page.waitForTimeout(400); }

    // the demo user is shared: its language goes back to English
    if (locale !== 'en') await setLocale('en');
    await page.close();
}
// the Starter demo: the same business without the pages of Pro
{
    const starter = site.replace('://demo.', '://starter.');
    const page = await browser.newPage({ viewport: { width: 1440, height: 1100 } });
    await page.goto(starter + '/login');
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
    await page.evaluate(() => { try { localStorage.setItem('hb.tour.' + window.ERP_TOUR.user, '1'); } catch (e) {} });
    await page.goto(starter + '/');
    await page.waitForLoadState('networkidle');
    const menu = await page.locator('.app-sidebar').innerText();
    for (const gone of ['Deals', 'Recurring Expenses', 'Expense Budgets', 'Depreciation', 'Liabilities', 'Partners', 'Analysis', 'Document Vault', 'Journal Entries', 'Sales & Purchases', 'Stock & Warehouses']) {
        check(`starter: "${gone}" is not in the menu`, !menu.includes(gone));
    }
    check('starter: the deals page answers with the upgrade page', (await page.goto(starter + '/deals')).status() === 403);
    await page.goto(starter + '/');
    await page.waitForLoadState('networkidle');
    await page.screenshot({ path: 'shots/starter-1-dashboard.png', fullPage: true });
    for (const [name, path] of [['cash', '/accounts/cash'], ['customers', '/accounts/customers'], ['saraf', '/accounts/saraf']]) {
        await page.goto(starter + path);
        await page.waitForLoadState('networkidle');
        check(`starter: ${name} shows a line for each currency`, (await page.locator('table.table-split tbody.split-group').count()) > 0);
        await page.screenshot({ path: `shots/starter-2-${name}.png` });
    }
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(starter + '/accounts/customers');
    await page.waitForLoadState('networkidle');
    await page.screenshot({ path: 'shots/starter-3-customers-phone.png' });
    await page.close();
}
await browser.close();
const failed = results.filter((r) => !r.ok).length;
console.log(`\n${results.length - failed} passed, ${failed} failed`);
process.exit(failed ? 1 : 0);
