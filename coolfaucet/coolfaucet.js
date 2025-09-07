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

    // Fill the email input and submit
    await page.type('input[name="email"]', email);
    await page.click('button[type="submit"]');

    // Wait for login to complete by checking for a selector that appears only after login
    await page.waitForSelector('.cf-stats', { timeout: 60000 }); // adjust if needed

    // Get cookies
    const cookies = await page.cookies();
    const cookieString = cookies.map(c => `${c.name}=${c.value}`).join('; ');

    // Show cookies in logs
    console.log('Captured cookies:');
    console.log(cookieString);

    // Save cookies to cookie.txt
    fs.writeFileSync('cookie.txt', cookieString);
    console.log('Cookie saved to cookie.txt');

    await browser.close();
})();
