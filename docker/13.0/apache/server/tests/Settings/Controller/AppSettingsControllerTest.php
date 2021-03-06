<?php
/**
 * @author Lukas Reschke <lukas@owncloud.com>
 *
 * @copyright Copyright (c) 2016, Lukas Reschke <lukas@statuscode.ch>
 * @copyright Copyright (c) 2015, ownCloud, Inc.
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace Tests\Settings\Controller;

use OC\App\AppStore\Bundles\BundleFetcher;
use OC\App\AppStore\Fetcher\AppFetcher;
use OC\App\AppStore\Fetcher\CategoryFetcher;
use OC\Installer;
use OC\Settings\Controller\AppSettingsController;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\ILogger;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use Test\TestCase;
use OCP\IRequest;
use OCP\IL10N;
use OCP\IConfig;
use OCP\INavigationManager;
use OCP\App\IAppManager;

/**
 * Class AppSettingsControllerTest
 *
 * @package Tests\Settings\Controller
 */
class AppSettingsControllerTest extends TestCase {
	/** @var AppSettingsController */
	private $appSettingsController;
	/** @var IRequest|\PHPUnit_Framework_MockObject_MockObject */
	private $request;
	/** @var IL10N|\PHPUnit_Framework_MockObject_MockObject */
	private $l10n;
	/** @var IConfig|\PHPUnit_Framework_MockObject_MockObject */
	private $config;
	/** @var INavigationManager|\PHPUnit_Framework_MockObject_MockObject */
	private $navigationManager;
	/** @var IAppManager|\PHPUnit_Framework_MockObject_MockObject */
	private $appManager;
	/** @var CategoryFetcher|\PHPUnit_Framework_MockObject_MockObject */
	private $categoryFetcher;
	/** @var AppFetcher|\PHPUnit_Framework_MockObject_MockObject */
	private $appFetcher;
	/** @var IFactory|\PHPUnit_Framework_MockObject_MockObject */
	private $l10nFactory;
	/** @var BundleFetcher|\PHPUnit_Framework_MockObject_MockObject */
	private $bundleFetcher;
	/** @var Installer|\PHPUnit_Framework_MockObject_MockObject */
	private $installer;
	/** @var IURLGenerator|\PHPUnit_Framework_MockObject_MockObject */
	private $urlGenerator;
	/** @var ILogger|\PHPUnit_Framework_MockObject_MockObject */
	private $logger;

	public function setUp() {
		parent::setUp();

		$this->request = $this->createMock(IRequest::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->expects($this->any())
			->method('t')
			->will($this->returnArgument(0));
		$this->config = $this->createMock(IConfig::class);
		$this->navigationManager = $this->createMock(INavigationManager::class);
		$this->appManager = $this->createMock(IAppManager::class);
		$this->categoryFetcher = $this->createMock(CategoryFetcher::class);
		$this->appFetcher = $this->createMock(AppFetcher::class);
		$this->l10nFactory = $this->createMock(IFactory::class);
		$this->bundleFetcher = $this->createMock(BundleFetcher::class);
		$this->installer = $this->createMock(Installer::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->logger = $this->createMock(ILogger::class);

		$this->appSettingsController = new AppSettingsController(
			'settings',
			$this->request,
			$this->l10n,
			$this->config,
			$this->navigationManager,
			$this->appManager,
			$this->categoryFetcher,
			$this->appFetcher,
			$this->l10nFactory,
			$this->bundleFetcher,
			$this->installer,
			$this->urlGenerator,
			$this->logger
		);
	}

	public function testListCategories() {
		$this->installer->expects($this->any())
			->method('isUpdateAvailable')
			->willReturn(false);
		$expected = new JSONResponse([
			[
				'id' => 'auth',
				'ident' => 'auth',
				'displayName' => 'Authentication & authorization',
			],
			[
				'id' => 'customization',
				'ident' => 'customization',
				'displayName' => 'Customization',
			],
			[
				'id' => 'files',
				'ident' => 'files',
				'displayName' => 'Files',
			],
			[
				'id' => 'integration',
				'ident' => 'integration',
				'displayName' => 'Integration',
			],
			[
				'id' => 'monitoring',
				'ident' => 'monitoring',
				'displayName' => 'Monitoring',
			],
			[
				'id' => 'multimedia',
				'ident' => 'multimedia',
				'displayName' => 'Multimedia',
			],
			[
				'id' => 'office',
				'ident' => 'office',
				'displayName' => 'Office & text',
			],
			[
				'id' => 'organization',
				'ident' => 'organization',
				'displayName' => 'Organization',
			],
			[
				'id' => 'social',
				'ident' => 'social',
				'displayName' => 'Social & communication',
			],
			[
				'id' => 'tools',
				'ident' => 'tools',
				'displayName' => 'Tools',
			],
		]);

		$this->categoryFetcher
			->expects($this->once())
			->method('get')
			->willReturn(json_decode('[{"id":"auth","translations":{"cs":{"name":"Autentizace & autorizace","description":"Aplikace poskytuj??c?? slu??by dodate??n??ho ov????en?? nebo p??ihl????en??"},"hu":{"name":"Azonos??t??s ??s hiteles??t??s","description":"Apps that provide additional authentication or authorization services"},"de":{"name":"Authentifizierung & Authorisierung","description":"Apps die zus??tzliche Autentifizierungs- oder Autorisierungsdienste bereitstellen"},"nl":{"name":"Authenticatie & authorisatie","description":"Apps die aanvullende authenticatie- en autorisatiediensten bieden"},"nb":{"name":"P??logging og tilgangsstyring","description":"Apper for ?? tilby ekstra p??logging eller tilgangsstyring"},"it":{"name":"Autenticazione e autorizzazione","description":"Apps that provide additional authentication or authorization services"},"fr":{"name":"Authentification et autorisations","description":"Applications qui fournissent des services d\'authentification ou d\'autorisations additionnels."},"ru":{"name":"???????????????????????????? ?? ??????????????????????","description":"Apps that provide additional authentication or authorization services"},"en":{"name":"Authentication & authorization","description":"Apps that provide additional authentication or authorization services"}}},{"id":"customization","translations":{"cs":{"name":"P??izp??soben??","description":"Motivy a aplikace m??n??c?? rozvr??en?? a u??ivatelsk?? rozhran??"},"it":{"name":"Personalizzazione","description":"Applicazioni di temi, modifiche della disposizione e UX"},"de":{"name":"Anpassung","description":"Apps zur ??nderung von Themen, Layout und Benutzererfahrung"},"hu":{"name":"Szem??lyre szab??s","description":"T??m??k, elrendez??sek felhaszn??l??i fel??let m??dos??t?? alkalmaz??sok"},"nl":{"name":"Maatwerk","description":"Thema\'s, layout en UX aanpassingsapps"},"nb":{"name":"Tilpasning","description":"Apper for ?? endre Tema, utseende og brukeropplevelse"},"fr":{"name":"Personalisation","description":"Th??mes, apparence et applications modifiant l\'exp??rience utilisateur"},"ru":{"name":"??????????????????","description":"Themes, layout and UX change apps"},"en":{"name":"Customization","description":"Themes, layout and UX change apps"}}},{"id":"files","translations":{"cs":{"name":"Soubory","description":"Aplikace roz??i??uj??c?? spr??vu soubor?? nebo aplikaci Soubory"},"it":{"name":"File","description":"Applicazioni di gestione dei file ed estensione dell\'applicazione FIle"},"de":{"name":"Dateien","description":"Dateimanagement sowie Erweiterungs-Apps f??r die Dateien-App"},"hu":{"name":"F??jlok","description":"F??jl kezel?? ??s kieg??sz??t?? alkalmaz??sok"},"nl":{"name":"Bestanden","description":"Bestandebeheer en uitbreidingen van bestand apps"},"nb":{"name":"Filer","description":"Apper for filh??ndtering og filer"},"fr":{"name":"Fichiers","description":"Applications de gestion de fichiers et extensions ?? l\'application Fichiers"},"ru":{"name":"??????????","description":"????????????????????: ?????????? ?? ???????????????????? ??????????????"},"en":{"name":"Files","description":"File management and Files app extension apps"}}},{"id":"integration","translations":{"it":{"name":"Integrazione","description":"Applicazioni che collegano Nextcloud con altri servizi e piattaforme"},"hu":{"name":"Integr??ci??","description":"Apps that connect Nextcloud with other services and platforms"},"nl":{"name":"Integratie","description":"Apps die Nextcloud verbinden met andere services en platformen"},"nb":{"name":"Integrasjon","description":"Apper som kobler Nextcloud med andre tjenester og plattformer"},"de":{"name":"Integration","description":"Apps die Nextcloud mit anderen Diensten und Plattformen verbinden"},"cs":{"name":"Propojen??","description":"Aplikace propojuj??c?? NextCloud s dal????mi slu??bami a platformami"},"fr":{"name":"Int??gration","description":"Applications qui connectent Nextcloud avec d\'autres services et plateformes"},"ru":{"name":"????????????????????","description":"????????????????????, ?????????????????????? Nextcloud ?? ?????????????? ???????????????? ?? ??????????????????????"},"en":{"name":"Integration","description":"Apps that connect Nextcloud with other services and platforms"}}},{"id":"monitoring","translations":{"nb":{"name":"Overv??king","description":"Apper for statistikk, systemdiagnose og aktivitet"},"it":{"name":"Monitoraggio","description":"Applicazioni di statistiche, diagnostica di sistema e attivit??"},"de":{"name":"??berwachung","description":"Datenstatistiken-, Systemdiagnose- und Aktivit??ten-Apps"},"hu":{"name":"Megfigyel??s","description":"Data statistics, system diagnostics and activity apps"},"nl":{"name":"Monitoren","description":"Gegevensstatistiek, systeem diagnose en activiteit apps"},"cs":{"name":"Kontrola","description":"Datov?? statistiky, diagn??zy syst??mu a aktivity aplikac??"},"fr":{"name":"Surveillance","description":"Applications de statistiques sur les donn??es, de diagnostics syst??mes et d\'activit??."},"ru":{"name":"????????????????????","description":"???????????????????? ????????????, ?????????????????????? ?????????????? ?? ???????????????????? ????????????????????"},"en":{"name":"Monitoring","description":"Data statistics, system diagnostics and activity apps"}}},{"id":"multimedia","translations":{"nb":{"name":"Multimedia","description":"Apper for lyd, film og bilde"},"it":{"name":"Multimedia","description":"Applicazioni per audio, video e immagini"},"de":{"name":"Multimedia","description":"Audio-, Video- und Bilder-Apps"},"hu":{"name":"Multim??dia","description":"Hang, vide?? ??s k??p alkalmaz??sok"},"nl":{"name":"Multimedia","description":"Audio, video en afbeelding apps"},"en":{"name":"Multimedia","description":"Audio, video and picture apps"},"cs":{"name":"Multim??dia","description":"Aplikace audia, videa a obr??zk??"},"fr":{"name":"Multim??dia","description":"Applications audio, vid??o et image"},"ru":{"name":"??????????????????????","description":"???????????????????? ??????????, ?????????? ?? ??????????????????????"}}},{"id":"office","translations":{"nb":{"name":"Kontorst??tte og tekst","description":"Apper for Kontorst??tte og tekstbehandling"},"it":{"name":"Ufficio e testo","description":"Applicazione per ufficio ed elaborazione di testi"},"de":{"name":"B??ro & Text","description":"B??ro- und Textverarbeitungs-Apps"},"hu":{"name":"Iroda ??s sz??veg","description":"Irodai ??s sz??veg feldolgoz?? alkalmaz??sok"},"nl":{"name":"Office & tekst","description":"Office en tekstverwerkingsapps"},"cs":{"name":"Kancel???? a text","description":"Aplikace pro kancel???? a zpracov??n?? textu"},"fr":{"name":"Bureautique & texte","description":"Applications de bureautique et de traitement de texte"},"en":{"name":"Office & text","description":"Office and text processing apps"}}},{"id":"organization","translations":{"nb":{"name":"Organisering","description":"Apper for tidsstyring, oppgaveliste og kalender"},"it":{"name":"Organizzazione","description":"Applicazioni di gestione del tempo, elenco delle cose da fare e calendario"},"hu":{"name":"Szervezet","description":"Id??beoszt??s, teend?? lista ??s napt??r alkalmaz??sok"},"nl":{"name":"Organisatie","description":"Tijdmanagement, takenlijsten en agenda apps"},"cs":{"name":"Organizace","description":"Aplikace pro spr??vu ??asu, pl??nov??n?? a kalend????e"},"de":{"name":"Organisation","description":"Time management, Todo list and calendar apps"},"fr":{"name":"Organisation","description":"Applications de gestion du temps, de listes de t??ches et d\'agendas"},"ru":{"name":"??????????????????????","description":"???????????????????? ???? ???????????????????? ????????????????, ???????????? ?????????? ?? ??????????????????"},"en":{"name":"Organization","description":"Time management, Todo list and calendar apps"}}},{"id":"social","translations":{"nb":{"name":"Sosialt og kommunikasjon","description":"Apper for meldinger, kontakth??ndtering og sosiale medier"},"it":{"name":"Sociale e comunicazione","description":"Applicazioni di messaggistica, gestione dei contatti e reti sociali"},"de":{"name":"Kommunikation","description":"Nachrichten-, Kontaktverwaltungs- und Social-Media-Apps"},"hu":{"name":"K??z??ss??gi ??s kommunik??ci??","description":"??zenetk??ld??, kapcsolat kezel?? ??s k??z??ss??gi m??dia alkalmaz??sok"},"nl":{"name":"Sociaal & communicatie","description":"Messaging, contactbeheer en social media apps"},"cs":{"name":"Soci??ln?? s??t?? a komunikace","description":"Aplikace pro zas??l??n?? zpr??v, spr??vu kontakt?? a soci??ln?? s??t??"},"fr":{"name":"Social & communication","description":"Applications de messagerie, de gestion de contacts et de r??seaux sociaux"},"ru":{"name":"???????????????????? ?? ??????????","description":"??????????????, ???????????????????? ???????????????????? ?? ???????????????????? ??????????-????????????????????"},"en":{"name":"Social & communication","description":"Messaging, contact management and social media apps"}}},{"id":"tools","translations":{"nb":{"name":"Verkt??y","description":"Alt annet"},"it":{"name":"Strumenti","description":"Tutto il resto"},"hu":{"name":"Eszk??z??k","description":"Minden m??s"},"nl":{"name":"Tools","description":"De rest"},"de":{"name":"Werkzeuge","description":"Alles Andere"},"en":{"name":"Tools","description":"Everything else"},"cs":{"name":"N??stroje","description":"V??e ostatn??"},"fr":{"name":"Outils","description":"Tout le reste"},"ru":{"name":"????????????????????","description":"??????-???? ??????"}}}]', true));

		$this->assertEquals($expected, $this->appSettingsController->listCategories());
	}

	public function testViewApps() {
		$this->bundleFetcher->expects($this->once())->method('getBundles')->willReturn([]);
		$this->installer->expects($this->any())
			->method('isUpdateAvailable')
			->willReturn(false);
		$this->config
			->expects($this->once())
			->method('getSystemValue')
			->with('appstoreenabled', true)
			->will($this->returnValue(true));
		$this->navigationManager
			->expects($this->once())
			->method('setActiveEntry')
			->with('core_apps');

		$policy = new ContentSecurityPolicy();
		$policy->addAllowedImageDomain('https://usercontent.apps.nextcloud.com');

		$expected = new TemplateResponse('settings',
			'settings-vue',
			[
				'serverData' => [
					'updateCount' => 0,
					'appstoreEnabled' => true,
					'bundles' => [],
					'developerDocumentation' => ''
				]
			],
			'user');
		$expected->setContentSecurityPolicy($policy);

		$this->assertEquals($expected, $this->appSettingsController->viewApps());
	}

	public function testViewAppsAppstoreNotEnabled() {
		$this->installer->expects($this->any())
			->method('isUpdateAvailable')
			->willReturn(false);
		$this->bundleFetcher->expects($this->once())->method('getBundles')->willReturn([]);
		$this->config
			->expects($this->once())
			->method('getSystemValue')
			->with('appstoreenabled', true)
			->will($this->returnValue(false));
		$this->navigationManager
			->expects($this->once())
			->method('setActiveEntry')
			->with('core_apps');

		$policy = new ContentSecurityPolicy();
		$policy->addAllowedImageDomain('https://usercontent.apps.nextcloud.com');

		$expected = new TemplateResponse('settings',
			'settings-vue',
			[
				'serverData' => [
					'updateCount' => 0,
					'appstoreEnabled' => false,
					'bundles' => [],
					'developerDocumentation' => ''
				]
			],
			'user');
		$expected->setContentSecurityPolicy($policy);

		$this->assertEquals($expected, $this->appSettingsController->viewApps());
	}
}
