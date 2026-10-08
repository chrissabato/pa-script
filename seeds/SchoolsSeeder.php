<?php

use Phinx\Seed\AbstractSeed;

/**
 * Willamette plus every opponent from the broadcast app's team list
 * (/claude/broadcast/www/config/teams.json): full name, short name,
 * mascot, logo, primary color, athletics website URL and CMS provider.
 * Logo values are filenames under public/logos/ (copied from
 * vmix-config/logos) or absolute URLs. The original school list came from
 * the "PA Scripts" Google Sheet's team lookup tab; every school from it is
 * in teams.json too.
 *
 * Idempotent and non-destructive: missing schools are inserted, and for
 * schools that already exist only empty (null) columns are filled, so
 * edits made on the Teams page are never overwritten by a re-run.
 *
 * Adjustments from teams.json:
 * - Typos fixed: "Ashland UNiversity", "Marian Unirversity",
 *   "Claffin" -> Claflin (matches its athletics.claflin.edu domain).
 * - "Calvin College" is stored under its current name, Calvin University
 *   (the row already existed here with games attached).
 * - Claremont-Mudd-Scripps [Men] mascot is Stags (source said Athenas;
 *   its own logo file is CMSStags.png).
 * - Life University domain "iferunningeagles.com" -> liferunningeagles.com.
 * - SUNY Fredonia logo "Fredonia" -> Fredonia.png.
 * - Domains are stored as full https:// URLs.
 */
class SchoolsSeeder extends AbstractSeed
{
    public function run(): void
    {
        $wanted = [
            ['name' => 'Willamette University', 'short_name' => 'Willamette', 'mascot' => 'Bearcats', 'logo' => 'Willamette.png', 'color' => '#791716', 'url' => 'https://wubearcats.com', 'cms_provider' => 'presto'],
            ['name' => 'Alfred State College', 'short_name' => 'Alfred State', 'mascot' => 'Pioneers', 'logo' => 'AlfredState.png', 'color' => '#00467F', 'url' => 'https://alfredstateathletics.com', 'cms_provider' => 'presto'],
            ['name' => 'Alma College', 'short_name' => 'Alma', 'mascot' => 'Scots', 'logo' => 'Alma.png', 'color' => '#005B5B', 'url' => null, 'cms_provider' => null],
            ['name' => 'Anderson University', 'short_name' => 'Anderson', 'mascot' => 'Ravens', 'logo' => 'Anderson.png', 'color' => null, 'url' => 'https://athletics.anderson.edu', 'cms_provider' => 'presto'],
            ['name' => 'Asbury University', 'short_name' => 'Asbury', 'mascot' => 'Eagles', 'logo' => 'Asbury.png', 'color' => '#49176D', 'url' => 'https://asburyeagles.com', 'cms_provider' => 'sidearm'],
            ['name' => 'Ashland University', 'short_name' => 'Ashland', 'mascot' => 'Eagles', 'logo' => null, 'color' => '#5C068C', 'url' => 'https://goashlandeagles.com', 'cms_provider' => 'sidearm'],
            ['name' => 'Augsburg University', 'short_name' => 'Augsburg', 'mascot' => 'Auggies', 'logo' => 'Augsburg.png', 'color' => '#8A2A2E', 'url' => null, 'cms_provider' => null],
            ['name' => 'Austin College', 'short_name' => 'Austin', 'mascot' => 'Kangaroos', 'logo' => 'Austin.png', 'color' => '#FF0000', 'url' => 'https://acroos.com', 'cms_provider' => 'presto'],
            ['name' => 'Berry College', 'short_name' => 'Berry', 'mascot' => 'Vikings', 'logo' => 'Berry.png', 'color' => '#00205C', 'url' => 'https://berryvikings.com', 'cms_provider' => 'sidearm'],
            ['name' => 'Bethany College', 'short_name' => 'Bethany', 'mascot' => 'Bisons', 'logo' => 'Bethany.png', 'color' => null, 'url' => 'https://bethanybison.com', 'cms_provider' => 'presto'],
            ['name' => 'Bethany Lutheran College', 'short_name' => 'Bethany Lutheran', 'mascot' => 'Vikings', 'logo' => 'BethanyLutheran.png', 'color' => '#AC1F2D', 'url' => 'https://blcvikings.com', 'cms_provider' => null],
            ['name' => 'Bethel University', 'short_name' => 'Bethel', 'mascot' => 'Pilots', 'logo' => null, 'color' => null, 'url' => 'https://bupilots.com', 'cms_provider' => 'sidearm'],
            ['name' => 'Bryan College', 'short_name' => 'Bryan', 'mascot' => 'Lions', 'logo' => null, 'color' => null, 'url' => 'https://bryanlions.com', 'cms_provider' => 'sidearm'],
            ['name' => 'Bushnell University', 'short_name' => 'Bushnell', 'mascot' => 'Beacons', 'logo' => 'Bushnell.png', 'color' => '#011E41', 'url' => 'https://bushnellbeacons.com', 'cms_provider' => 'sidearm'],
            ['name' => 'Cal Lutheran University [Men]', 'short_name' => 'Cal Lutheran', 'mascot' => 'Kingsmen', 'logo' => 'CalLutheran.png', 'color' => '#3B2360', 'url' => 'https://www.clusports.com', 'cms_provider' => 'sidearm'],
            ['name' => 'Cal Lutheran University [Women]', 'short_name' => 'Cal Lutheran', 'mascot' => 'Regals', 'logo' => 'CalLutheran.png', 'color' => '#3B2360', 'url' => 'https://www.clusports.com', 'cms_provider' => 'sidearm'],
            ['name' => 'California Institute of Technology', 'short_name' => 'Caltech', 'mascot' => 'Beavers', 'logo' => 'Caltech.png', 'color' => '#E87E24', 'url' => 'https://gocaltech.com', 'cms_provider' => 'sidearm'],
            ['name' => 'Calvin University', 'short_name' => 'Calvin', 'mascot' => 'Knights', 'logo' => 'Calvin.png', 'color' => '#8D2133', 'url' => null, 'cms_provider' => null],
            ['name' => 'Carolina University', 'short_name' => 'Carolina', 'mascot' => 'Bruins', 'logo' => null, 'color' => null, 'url' => 'https://cubruins.com', 'cms_provider' => 'sidearm'],
            ['name' => 'Centenary College', 'short_name' => 'Centenary', 'mascot' => 'Gents', 'logo' => 'Centenary.png', 'color' => null, 'url' => 'https://gocentenary.com', 'cms_provider' => 'presto'],
            ['name' => 'Chapman University', 'short_name' => 'Chapman', 'mascot' => 'Panthers', 'logo' => 'Chapman.png', 'color' => '#98012E', 'url' => 'https://chapmanathletics.com', 'cms_provider' => 'presto'],
            ['name' => 'Claflin University', 'short_name' => 'Claflin', 'mascot' => 'Panthers', 'logo' => 'https://presto-sport-static.s3.us-east-1.amazonaws.com/logos/30058.png', 'color' => '#A81800', 'url' => 'https://athletics.claflin.edu', 'cms_provider' => 'sidearm'],
            ['name' => 'Claremont-Mudd-Scripps [Men]', 'short_name' => 'CMS', 'mascot' => 'Stags', 'logo' => 'CMSStags.png', 'color' => '#EDAA1E', 'url' => 'https://cmsathletics.org', 'cms_provider' => 'sidearm'],
            ['name' => 'Claremont-Mudd-Scripps [Women]', 'short_name' => 'CMS', 'mascot' => 'Athenas', 'logo' => 'CMSAthenas.png', 'color' => '#EDAA1E', 'url' => 'https://cmsathletics.org', 'cms_provider' => 'sidearm'],
            ['name' => 'Coker University', 'short_name' => 'Coker', 'mascot' => 'Cobras', 'logo' => 'https://presto-sport-static.s3.us-east-1.amazonaws.com/logos/1023.png', 'color' => '#F0C018', 'url' => 'https://cokercobras.com', 'cms_provider' => 'presto'],
            ['name' => 'College of Idaho', 'short_name' => 'College of Idaho', 'mascot' => 'Yotes', 'logo' => 'CollegeOfIdaho.png', 'color' => '#5A2049', 'url' => null, 'cms_provider' => null],
            ['name' => 'Corban University', 'short_name' => 'Corban', 'mascot' => 'Warriors', 'logo' => 'Corban.png', 'color' => '#003D71', 'url' => 'https://corbanwarriors.com', 'cms_provider' => 'sidearm'],
            ['name' => 'Dean College', 'short_name' => 'Dean', 'mascot' => 'Bulldogs', 'logo' => 'Dean.png', 'color' => null, 'url' => 'https://deanbulldogs.com', 'cms_provider' => 'sidearm'],
            ['name' => 'Denison University', 'short_name' => 'Denison', 'mascot' => 'Big Red', 'logo' => 'Denison.png', 'color' => null, 'url' => 'https://denisonbigred.com', 'cms_provider' => 'sidearm'],
            ['name' => 'Douglas College', 'short_name' => 'Douglas', 'mascot' => 'Royals', 'logo' => 'Douglas.png', 'color' => '#38673D', 'url' => 'https://douglascollegeroyals.ca', 'cms_provider' => 'presto'],
            ['name' => 'Eastern Oregon University', 'short_name' => 'Eastern Oregon', 'mascot' => 'Mountaineers', 'logo' => 'EasternOregon.png', 'color' => '#0A1834', 'url' => 'https://eousports.com', 'cms_provider' => 'sidearm'],
            ['name' => 'Emory University', 'short_name' => 'Emory', 'mascot' => 'Eagles', 'logo' => 'Emory.png', 'color' => '#14315A', 'url' => 'https://emoryathletics.com', 'cms_provider' => 'presto'],
            ['name' => 'Evergreen State College', 'short_name' => 'Evergreen State', 'mascot' => 'Geoducks', 'logo' => 'EvergreenState.png', 'color' => '#006838', 'url' => null, 'cms_provider' => null],
            ['name' => 'George Fox University', 'short_name' => 'George Fox', 'mascot' => 'Bruins', 'logo' => 'GeorgeFox.png', 'color' => '#002D62', 'url' => 'https://athletics.georgefox.edu', 'cms_provider' => 'sidearm'],
            ['name' => 'Georgetown College', 'short_name' => 'Georgetown', 'mascot' => 'Tigers', 'logo' => null, 'color' => null, 'url' => 'https://georgetowncollegeathletics.com', 'cms_provider' => 'presto'],
            ['name' => 'Georgia Gwinnett College', 'short_name' => 'Georgia Gwinnett', 'mascot' => 'Grizzlies', 'logo' => null, 'color' => null, 'url' => 'https://ggcathletics.com', 'cms_provider' => 'sidearm'],
            ['name' => 'Hamline University', 'short_name' => 'Hamline', 'mascot' => 'Pipers', 'logo' => 'Hamline.png', 'color' => '#A41F34', 'url' => null, 'cms_provider' => null],
            ['name' => 'Hannibal-LaGrange University', 'short_name' => 'Hannibal-LaGrange', 'mascot' => 'Trojans', 'logo' => null, 'color' => null, 'url' => 'https://hlgtrojans.com', 'cms_provider' => 'sidearm'],
            ['name' => 'Hardin-Simmons University', 'short_name' => 'Hardin-Simmons', 'mascot' => 'Cowboys', 'logo' => 'HardinSimmons.png', 'color' => '#592B80', 'url' => null, 'cms_provider' => null],
            ['name' => 'Hope College', 'short_name' => 'Hope', 'mascot' => 'Flying Dutch', 'logo' => 'Hope.png', 'color' => '#002B5C', 'url' => 'https://athletics.hope.edu', 'cms_provider' => 'sidearm'],
            ['name' => 'Johnson University', 'short_name' => 'Johnson', 'mascot' => 'Royals', 'logo' => null, 'color' => null, 'url' => 'https://johnsonroyals.com', 'cms_provider' => 'presto'],
            ['name' => 'LeTourneau University', 'short_name' => 'LeTourneau', 'mascot' => 'Yellowjackets', 'logo' => 'LeTourneau.png', 'color' => null, 'url' => 'https://letuathletics.com', 'cms_provider' => 'sidearm'],
            ['name' => 'Lewis & Clark College', 'short_name' => 'Lewis & Clark', 'mascot' => 'River Otters', 'logo' => 'LewisandClark.png', 'color' => '#ED7738', 'url' => 'https://golcathletics.com', 'cms_provider' => 'sidearm'],
            ['name' => 'Life University', 'short_name' => 'Life', 'mascot' => 'Running Eagles', 'logo' => null, 'color' => null, 'url' => 'https://liferunningeagles.com', 'cms_provider' => 'sidearm'],
            ['name' => 'Linfield University', 'short_name' => 'Linfield', 'mascot' => 'Wildcats', 'logo' => 'Linfield.png', 'color' => '#46166B', 'url' => 'https://golinfieldwildcats.com', 'cms_provider' => 'sidearm'],
            ['name' => 'Marian University', 'short_name' => 'Marian', 'mascot' => 'Knights', 'logo' => null, 'color' => null, 'url' => 'https://muknights.com', 'cms_provider' => 'sidearm'],
            ['name' => 'Milwaukee School of Engineering', 'short_name' => 'MSOE', 'mascot' => 'Raiders', 'logo' => 'msoe.png', 'color' => '#C10230', 'url' => 'https://msoeraiders.com', 'cms_provider' => 'sidearm'],
            ['name' => 'Misericordia University', 'short_name' => 'Misericordia', 'mascot' => 'Cougars', 'logo' => 'Misericordia.png', 'color' => '#006EB9', 'url' => 'https://athletics.misericordia.edu', 'cms_provider' => 'sidearm'],
            ['name' => 'Montreat College', 'short_name' => 'Montreat', 'mascot' => 'Cavaliers', 'logo' => null, 'color' => null, 'url' => 'https://montreatcavaliers.com', 'cms_provider' => 'sidearm'],
            ['name' => 'Mount Vernon Nazarene', 'short_name' => 'Mount Vernon', 'mascot' => 'Cougars', 'logo' => null, 'color' => null, 'url' => 'https://mvnucougars.com', 'cms_provider' => 'presto'],
            ['name' => 'Multnomah University', 'short_name' => 'Multnomah', 'mascot' => 'Lions', 'logo' => 'Multnomah.png', 'color' => '#00573D', 'url' => 'https://gomulions.com', 'cms_provider' => 'sidearm'],
            ['name' => 'North Central College', 'short_name' => 'North Central', 'mascot' => 'Cardinals', 'logo' => 'NorthCentral.png', 'color' => '#B20838', 'url' => 'https://northcentralcardinals.com', 'cms_provider' => 'sidearm'],
            ['name' => 'Northern Michigan University', 'short_name' => 'Northern Michigan', 'mascot' => 'Wildcats', 'logo' => null, 'color' => '#095339', 'url' => 'https://nmuwildcats.com', 'cms_provider' => 'sidearm'],
            ['name' => 'Northwest Indian College', 'short_name' => 'Northwest Indian', 'mascot' => 'Eagles', 'logo' => 'NorthwestIndian.png', 'color' => '#C24227', 'url' => null, 'cms_provider' => null],
            ['name' => 'Oberlin College', 'short_name' => 'Oberlin', 'mascot' => 'Yeo', 'logo' => 'Oberlin.png', 'color' => '#A51E2F', 'url' => 'https://goyeo.com', 'cms_provider' => 'sidearm'],
            ['name' => 'Occidental College', 'short_name' => 'Occidental', 'mascot' => 'Tigers', 'logo' => 'Occidental.png', 'color' => '#F68428', 'url' => 'https://oxyathletics.com', 'cms_provider' => 'presto'],
            ['name' => 'Oglethorpe University', 'short_name' => 'Oglethorpe', 'mascot' => 'Stormy Petrels', 'logo' => 'Oglethorpe.png', 'color' => '#FFDD00', 'url' => 'https://gopetrels.com', 'cms_provider' => 'sidearm'],
            ['name' => 'Oregon Tech', 'short_name' => 'Oregon Tech', 'mascot' => 'Owls', 'logo' => 'OregonInstituteTechnology.png', 'color' => '#003768', 'url' => 'https://oregontechowls.com', 'cms_provider' => 'sidearm'],
            ['name' => 'Pacific Lutheran University', 'short_name' => 'Pacific Lutheran', 'mascot' => 'Lutes', 'logo' => 'PacificLutheran.png', 'color' => '#FFC423', 'url' => 'https://golutes.com', 'cms_provider' => 'sidearm'],
            ['name' => 'Pacific Union College', 'short_name' => 'Pacific Union', 'mascot' => 'Pioneers', 'logo' => 'PacificUnion.png', 'color' => '#AD9B75', 'url' => 'https://pioneersathletics.com', 'cms_provider' => 'presto'],
            ['name' => 'Pacific University', 'short_name' => 'Pacific', 'mascot' => 'Boxers', 'logo' => 'Pacific.png', 'color' => '#B4153A', 'url' => 'https://goboxers.com', 'cms_provider' => 'sidearm'],
            ['name' => 'Pomona-Pitzer', 'short_name' => 'Pomona-Pitzer', 'mascot' => 'Sagehens', 'logo' => 'PomonaPitzer.png', 'color' => '#003CA6', 'url' => 'https://sagehens.com', 'cms_provider' => 'sidearm'],
            ['name' => 'Portland Bible College', 'short_name' => 'Portland Bible', 'mascot' => 'Arrows', 'logo' => 'PortlandBible.png', 'color' => '#EE2421', 'url' => null, 'cms_provider' => null],
            ['name' => 'Reinhardt University', 'short_name' => 'Reinhardt', 'mascot' => 'Eagles', 'logo' => null, 'color' => null, 'url' => 'https://reinhardteagles.com', 'cms_provider' => 'presto'],
            ['name' => 'Salem State University', 'short_name' => 'Salem State', 'mascot' => 'Vikings', 'logo' => 'salemstate.png', 'color' => null, 'url' => 'https://salemstatevikings.com', 'cms_provider' => 'presto'],
            ['name' => 'Schreiner University', 'short_name' => 'Schreiner', 'mascot' => 'Mountaineers', 'logo' => 'Schreiner.png', 'color' => null, 'url' => 'https://schreinermountaineers.com', 'cms_provider' => 'presto_old'],
            ['name' => 'Shawnee State University', 'short_name' => 'Shawnee', 'mascot' => 'Bears', 'logo' => null, 'color' => null, 'url' => 'https://ssubears.com', 'cms_provider' => 'sidearm'],
            ['name' => 'Simpson University', 'short_name' => 'Simpson', 'mascot' => 'Red Hawks', 'logo' => 'Simpson.png', 'color' => '#D2232A', 'url' => 'https://simpsonredhawks.com', 'cms_provider' => 'sidearm'],
            ['name' => 'Southwestern Oregon CC', 'short_name' => 'Southwestern Oregon', 'mascot' => 'Lakers', 'logo' => 'SWOregon.png', 'color' => '#033E7D', 'url' => null, 'cms_provider' => null],
            ['name' => 'Southwestern University', 'short_name' => 'Southwestern', 'mascot' => 'Pirates', 'logo' => null, 'color' => null, 'url' => 'https://southwesternpirates.com', 'cms_provider' => 'sidearm'],
            ['name' => 'SUNY Cortland', 'short_name' => 'Cortland', 'mascot' => 'Red Dragons', 'logo' => 'Cortland.png', 'color' => null, 'url' => 'https://cortlandreddragons.com', 'cms_provider' => 'sidearm'],
            ['name' => 'SUNY Fredonia', 'short_name' => 'Fredonia', 'mascot' => 'Blue Devils', 'logo' => 'Fredonia.png', 'color' => null, 'url' => 'https://fredoniabluedevils.com', 'cms_provider' => 'sidearm'],
            ['name' => 'SUNY Oneonta', 'short_name' => 'Oneonta', 'mascot' => 'Red Dragons', 'logo' => 'Oneonta.png', 'color' => '#D3282F', 'url' => 'https://oneontaathletics.com', 'cms_provider' => 'sidearm'],
            ['name' => 'Tennessee Wesleyan', 'short_name' => 'Tennessee Wesleyan', 'mascot' => 'Bulldogs', 'logo' => null, 'color' => null, 'url' => 'https://twbulldogs.com', 'cms_provider' => 'presto'],
            ['name' => 'Trine University', 'short_name' => 'Trine', 'mascot' => 'Thunder', 'logo' => 'Trine.png', 'color' => null, 'url' => 'https://trinethunder.com', 'cms_provider' => 'sidearm'],
            ['name' => 'UC Santa Cruz', 'short_name' => 'Santa Cruz', 'mascot' => 'Banana Slugs', 'logo' => 'UCSantaCruz.png', 'color' => '#091F40', 'url' => 'https://goslugs.com', 'cms_provider' => 'sidearm'],
            ['name' => 'Union Commonwealth University', 'short_name' => 'Union', 'mascot' => 'Bulldogs', 'logo' => null, 'color' => null, 'url' => 'https://gounionbulldogs.com', 'cms_provider' => 'presto'],
            ['name' => 'University of La Verne', 'short_name' => 'La Verne', 'mascot' => 'Leopards', 'logo' => 'LaVerne.png', 'color' => '#014711', 'url' => 'https://leopardathletics.com', 'cms_provider' => 'sidearm'],
            ['name' => 'University of Puget Sound', 'short_name' => 'Puget Sound', 'mascot' => 'Loggers', 'logo' => 'PugetSound.png', 'color' => '#7A222F', 'url' => 'https://loggerathletics.com', 'cms_provider' => 'sidearm'],
            ['name' => 'University of Redlands', 'short_name' => 'Redlands', 'mascot' => 'Bulldogs', 'logo' => 'Redlands.png', 'color' => '#80766B', 'url' => 'https://goredlands.com', 'cms_provider' => 'sidearm'],
            ['name' => 'University of the South', 'short_name' => 'Sewanee', 'mascot' => 'Tigers', 'logo' => 'Sewanee.png', 'color' => '#5B2D82', 'url' => 'https://sewaneetigers.com', 'cms_provider' => 'sidearm'],
            ['name' => 'University of Wisconsin-Oshkosh', 'short_name' => 'Oshkosh', 'mascot' => 'Titans', 'logo' => 'WisconsinOshkosh.png', 'color' => '#FDB728', 'url' => 'https://uwoshkoshtitans.com', 'cms_provider' => 'sidearm'],
            ['name' => 'University of Wisconsin-River Falls', 'short_name' => 'River Falls', 'mascot' => 'Falcons', 'logo' => 'WisconsinRiverFalls.png', 'color' => '#CD1041', 'url' => 'https://uwrfsports.com', 'cms_provider' => 'sidearm'],
            ['name' => 'Ursuline College', 'short_name' => 'Ursuline', 'mascot' => 'Arrows', 'logo' => 'Ursuline.png', 'color' => null, 'url' => 'https://ursulinearrows.com', 'cms_provider' => 'sidearm'],
            ['name' => 'Vancouver Island University', 'short_name' => 'Vancouver Island', 'mascot' => 'Mariners', 'logo' => 'VancouverIsland.png', 'color' => '#009BDB', 'url' => null, 'cms_provider' => null],
            ['name' => 'Walla Walla University', 'short_name' => 'Walla Walla', 'mascot' => 'Wolves', 'logo' => 'WallaWalla.png', 'color' => '#404726', 'url' => 'https://uwolves.com', 'cms_provider' => 'sidearm'],
            ['name' => 'Warner Pacific University', 'short_name' => 'Warner Pacific', 'mascot' => 'Knights', 'logo' => 'WarnerPacific.png', 'color' => '#5FB2E4', 'url' => 'https://wpuknights.com', 'cms_provider' => 'sidearm'],
            ['name' => 'Washington and Lee University', 'short_name' => 'Washington and Lee', 'mascot' => 'Generals', 'logo' => 'WashingtonandLee.png', 'color' => '#000399', 'url' => 'https://generalssports.com', 'cms_provider' => 'sidearm'],
            ['name' => 'West Coast Baptist College', 'short_name' => 'West Coast Baptist', 'mascot' => 'Eagles', 'logo' => 'WestCoastBaptist.png', 'color' => '#A82327', 'url' => null, 'cms_provider' => null],
            ['name' => 'Western Oregon University', 'short_name' => 'Western Oregon', 'mascot' => 'Wolves', 'logo' => 'WesternOregon.png', 'color' => '#E51937', 'url' => 'https://wouwolves.com', 'cms_provider' => 'sidearm'],
            ['name' => 'Whitman College', 'short_name' => 'Whitman', 'mascot' => 'Blues', 'logo' => 'Whitman.png', 'color' => '#192858', 'url' => 'https://athletics.whitman.edu', 'cms_provider' => 'sidearm'],
            ['name' => 'Whittier College', 'short_name' => 'Whittier', 'mascot' => 'Poets', 'logo' => 'Whittier.png', 'color' => '#490E67', 'url' => 'https://wcpoets.com', 'cms_provider' => 'sidearm'],
            ['name' => 'Whitworth University', 'short_name' => 'Whitworth', 'mascot' => 'Pirates', 'logo' => 'Whitworth.png', 'color' => '#CD1041', 'url' => 'https://whitworthpirates.com', 'cms_provider' => 'sidearm'],
        ];

        $insert = [];
        foreach ($wanted as $row) {
            $existing = $this->fetchRow(sprintf(
                "select id from schools where name = '%s'",
                str_replace("'", "''", $row['name'])
            ));

            if ($existing === false) {
                $insert[] = $row;
                continue;
            }

            $sets = [];
            foreach ($row as $column => $value) {
                if ($column === 'name' || $value === null) {
                    continue;
                }
                $sets[] = sprintf(
                    '%1$s = coalesce(%1$s, \'%2$s\')',
                    $column,
                    str_replace("'", "''", $value)
                );
            }
            if ($sets !== []) {
                $this->execute(sprintf(
                    'update schools set %s where id = %d',
                    implode(', ', $sets),
                    (int) $existing['id']
                ));
            }
        }

        if ($insert !== []) {
            $this->table('schools')->insert($insert)->saveData();
        }
    }
}
