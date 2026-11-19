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
    await add.click();
    const modal = page.locator('.erp-form-modal.show');
    await modal.waitFor({ timeout: 15000 }).catch(() => {});
    check(`${label}: Add opens a modal`, await modal.isVisible(), page.url());
    check(`${label}: the address stays on the list`, !/\/create$/.test(page.url()), page.url());
    await page.waitForTimeout(400);
    await page.screenshot({ path: `shots/${label}-3-add-customer.png` });

    // sent empty: the form comes back in the modal with what is missing marked
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
    // the demo user is shared: its language goes back to English
    if (locale !== 'en') await setLocale('en');
    await page.close();
}
await browser.close();
const failed = results.filter((r) => !r.ok).length;
console.log(`\n${results.length - failed} passed, ${failed} failed`);
process.exit(failed ? 1 : 0);
