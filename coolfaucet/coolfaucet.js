const puppeteer = require('puppeteer');
const fs = require('fs');

(async () => {
    const email = process.env.FAUCET_LOGIN;

    const browser = await puppeteer.launch({
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });
    const page = await browser.newPage();

    await page.goto('https://coolfaucet.hu/', { waitUntil: 'domcontentloaded', timeout: 0 });

    // login
    await page.type('input[name="email"]', email);
    await page.click('button[type="submit"]');

    // tunggu sampai balance muncul
    await page.waitForSelector('.stat-item span.ms-2', { timeout: 60000 });

    // ambil teks balance
    const balance = await page.$eval(
        '.stat-item span.ms-2',
        el => el.innerText
    );

    console.log('=== LOGIN SUCCESS ===');
    console.log('Balance:', balance);

    // ambil cookies
    const cookies = await page.cookies();
    const cookieString = cookies.map(c => `${c.name}=${c.value}`).join('; ');

    console.log('Captured cookies:');
    console.log(cookieString);

    fs.writeFileSync('cookie.txt', cookieString);
    console.log('Cookie saved to cookie.txt');

    await browser.close();
})();
