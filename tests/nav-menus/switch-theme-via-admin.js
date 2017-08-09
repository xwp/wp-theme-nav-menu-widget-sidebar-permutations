// jshint ignore: start
// Load up the Customizer to switch themes in the context of a given changeset_uuid

const args = require( 'minimist' )( process.argv.slice( 2 ) );

if ( ! args.url ) {
	console.log( 'Error: Missing `url` arg.' );
	process.exit( 1 );
}
const url = args.url.replace( /\/$/, '' ); // untrailingslashit.

if ( ! args.theme ) {
	console.log( 'Error: Missing `theme` arg.' );
	process.exit( 1 );
}

const CHROME_PATH = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const PORT = 9515;
const webdriverio = require( 'webdriverio' );
const chromedriver = require( 'chromedriver' );

chromedriver.start([
	'--url-base=wd/hub',
	`--port=${PORT}`,
	'--verbose'
]);

(async () => {

	const opts = {
		port: PORT,
		desiredCapabilities: {
			browserName: 'chrome',
			chromeOptions: {
				binary: CHROME_PATH,
				args: [ '--headless' ]
			}
		}
	};

	let exitCode = 0;
	const browser = webdriverio.remote( opts ).init();
	try {
		let result;

		// Login.
		await browser.url( `${ url }/wp-admin/` );
		await browser.setValue( '#user_login', args.username || 'admin' );
		await browser.setValue( '#user_pass', args.password || 'admin' );
		await browser.click( '#wp-submit' );

		// Load themes admin screen.
		await browser.url( `${ url }/wp-admin/themes.php?search=${ args.theme }` );

		await browser.click( `[data-slug="${ args.theme }"] .button.activate` );
	} catch ( error ) {
		console.error( `Error: ${ error.message }` );
		exitCode = 1;
	} finally {
		chromedriver.stop();
		browser.end().then(
			() => {
				process.exit( exitCode )
			},
			( err ) => {
				console.log( `Error: ${ err.message }` );
				process.exit( 1 )
			}
		);
	}

})();
