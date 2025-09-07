const puppeteer = require('puppeteer');

(async () => {
    const email = process.env.FAUCET_LOGIN;

    const browser = await puppeteer.launch({
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });
    const page = await browser.newPage();

    await page.goto('https://coolfaucet.hu/', { waitUntil: 'networkidle2' });

    // Fill the email input and submit
    await page.type('input[name="email"]', email);
    await page.click('button[type="submit"]');

    // Wait for login to complete
    await page.waitForNavigation({ waitUntil: 'networkidle2' });

    // Get cookies
    const cookies = await page.cookies();
    const cookieString = cookies.map(c => `${c.name}=${c.value}`).join('; ');

    // Show cookies in logs
    console.log('Captured cookies:');
    console.log(cookieString);

    await browser.close();
})();
