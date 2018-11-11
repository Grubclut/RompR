<?php
include ("includes/vars.php");
include ("includes/functions.php");
include ("international.php");
include ("collection/collection.php");
include ("player/mpd/connection.php");
include ("backends/sql/backend.php");
require_once ("utils/imagefunctions.php");

// 2 Changes recently
// Don't create the entire playlist array and then json_encode it all as one - rather json_encode
// each track as they go
// Don't use collection data models, we can parse the mpd tag output directly

// BEFORE
// 10:12:36 : TIMTINGS            == Time Spent Reading Socket Data                      : 0.59719300270081
// 10:12:36 : TIMTINGS            == Time Spent Parsing Socket Data                      : 0.073909997940063
// 10:12:36 : TIMTINGS            == Time Spent Checking Database                        : 0
// 10:12:36 : TIMTINGS            == Time Spent Putting Stuff into Collection Structures : 2.8882780075073
// 10:12:36 : TIMINGS             == Whole Process took 00:04
// 10:12:36 : COLLECTION          Peak Memory Used Was 68,518,896 bytes  - meaning we used 67,215,544 bytes.
// 10:12:36 : TIMINGS             ======================================================================

// AFTER
// 13:01:48 : TIMTINGS            == Time Spent Reading Socket Data             : 0.53309297561646
// 13:01:48 : TIMTINGS            == Time Spent Parsing Socket Data             : 0.067625522613525
// 13:01:48 : TIMTINGS            == Time Spent Checking Database               : 2.6249232292175
// 13:01:48 : TIMTINGS            == Time Spent Creating Playlist Array         : 0.16455101966858
// 13:01:48 : TIMINGS             == Whole Process took 00:03
// 13:01:48 : COLLECTION          Peak Memory Used Was 1,589,536 bytes  - meaning we used 286,472 bytes.
// 10:12:36 : TIMINGS             ======================================================================

// Meaning the data structures and json encode of the entire playlist used 67MB!! For only 4000 tracks.

header('Content-Type: application/json; charset=utf-8');
debuglog("======================================================================","TIMINGS",4);
debuglog("== Starting Playlist Update","TIMINGS",4);
$initmem = memory_get_usage();
$now2 = time();
$doneone = false;
print '[';
doCollection("playlistinfo");
print ']';
ob_flush();
debuglog("== Time Spent Reading Socket Data             : ".$parse_time,"TIMTINGS",4);
debuglog("== Time Spent Parsing Socket Data             : ".$rtime,"TIMTINGS",4);
debuglog("== Time Spent Checking Database               : ".$db_time,"TIMTINGS",4);
debuglog("== Time Spent Creating Playlist Array         : ".$coll_time,"TIMTINGS",4);
debuglog("== Whole Process took ".format_time(time() - $now2),"TIMINGS",4);
$peakmem = memory_get_peak_usage();
$ourmem = $peakmem - $initmem;
debuglog("Peak Memory Used Was ".number_format($peakmem)." bytes  - meaning we used ".number_format($ourmem)." bytes.","COLLECTION",4);
debuglog("======================================================================","TIMINGS",4);
debuglog("Playlist Output Is Done","GETPLAYLIST");

function doNewPlaylistFile(&$filedata) {
    global $prefs;
    global $foundartists;
    global $doneone;
    global $filehandle;

    $t = $filedata['Title'];
    // We can't return NULL in the JSON data for some reason that escapes me
    if ($t === null) $t = "";
    $albumartist = format_sortartist($filedata);

    // Bloody spotify often returns album artist = A & B but track artists 'B' and 'A'.
    // This screws up the playcount stats. They're also not consistent with
    // capitalisation of articles in Album Artists
    $tartist = format_artist($filedata['Artist'],'');

    $tartistr = '';
    if (is_array($filedata['Artist'])) {
        $tartistr = format_artist(array_reverse($filedata['Artist']),'');
    }

    if (strtolower($tartistr) == strtolower($albumartist)) {
        $filedata['Artist'] = array_reverse($filedata['Artist']);
        $tartist = $tartistr;
        $albumartist = $tartistr;
    }

    $albumimage = new baseAlbumImage(array(
        'baseimage' => $filedata['X-AlbumImage'],
        'artist' => artist_for_image($filedata['type'], $albumartist),
        'album' => $filedata['Album']
    ));
    $albumimage->check_image($filedata['domain'], $filedata['type'], true);

    if ($doneone) {
        print ', ';
    } else {
        $doneone = true;
    }

    $info = array(
        "title" => $t,
        "album" => $filedata['Album'],
        "trackartist" => $tartist,
        "albumartist" => $albumartist,
        "duration" => $filedata['Time'],
        "type" => $filedata['type'],
        "date" => getYear($filedata['Date']),
        "tracknumber" => $filedata['Track'],
        "disc" => $filedata['Disc'],
        "location" => $filedata['file'],
        "backendid" => (int) $filedata['Id'],
        "streamid" => $filedata['StreamIndex'],
        "dir" => rawurlencode($filedata['folder']),
        "key" => $albumimage->get_image_key(),
        "images" => $albumimage->get_images(),
        "stream" => $filedata['stream'],
        "playlistpos" => $filedata['Pos'],
        "genre" => $filedata['Genre'],
        "imgsearched" => $filedata['Searched'],
        "progress" => 0,
        "comment" => array_key_exists('Comment', $filedata) ? $filedata['Comment'] : '',
        "metadata" => array(
            "iscomposer" => 'false',
            "artists" => array(),
            "album" => array(
                "name" => trim($filedata['Album']),
                "artist" => trim($albumartist),
                "musicbrainz_id" => trim($filedata['MUSICBRAINZ_ALBUMID']),
                "uri" => $filedata['X-AlbumUri']
            ),
            "track" => array(
                "name" => trim($filedata['Title']),
                "musicbrainz_id" => trim($filedata['MUSICBRAINZ_TRACKID']),
            ),
        )
    );

    if ($filedata['X-AlbumUri'] && getDomain($filedata['X-AlbumUri']) == 'spotify') {
        $info['metadata']['album']['spotify'] = array(
            'id' => substr($filedata['X-AlbumUri'], 14)
        );
    }

    $foundartists = array();

    // All kinds of places we get artist names from:
    // Composer, Performer, Track Artist, Album Artist
    // Note that we filter duplicates
    // This creates the metadata array used by the info panel and nowplaying -
    // Metadata such as scrobbles and ratings will still use the Album Artist

    if ($prefs['displaycomposer']) {
        // The user has chosen to display Composer/Perfomer information
        // Here check:
        // a) There is composer/performer information AND
        // bi) Specific Genre Selected, Track Has Genre, Genre Matches Specific Genre OR
        // bii) No Specific Genre Selected, Track Has Genre
        if (($filedata['Composer'] !== null || $filedata['Performer'] !== null) &&
            (($prefs['composergenre'] && $filedata['Genre'] &&
                checkComposerGenre($filedata['Genre'], $prefs['composergenrename'])) ||
            (!$prefs['composergenre'] && $filedata['Genre'])))
        {
            // Track Genre matches selected 'Sort By Composer' Genre
            // Display Compoer - Performer - AlbumArtist
            do_composers($filedata, $info);
            do_performers($filedata, $info);
            // The album artist probably won't be required in this case, but use it just in case
            do_albumartist($filedata, $info, $albumartist);
            // Don't do track artist as with things tagged like this this is usually rubbish
        } else {
            // Track Genre Does Not Match Selected 'Sort By Composer' Genre
            // Or there is no composer/performer info
            // Do Track Artist - Album Artist - Composer - Performer
            do_track_artists($filedata, $info, $albumartist);
            do_albumartist($filedata, $info, $albumartist);
            do_performers($filedata, $info);
            do_composers($filedata, $info);
        }
        if ($filedata['Composer'] !== null || $filedata['Performer'] !== null) {
            $info['metadata']['iscomposer'] = 'true';
        }
    } else {
        // The user does not want Composer/Performer information
        do_track_artists($filedata, $info, $albumartist);
        do_albumartist($filedata, $info, $albumartist);
    }

    if (count($info['metadata']['artists']) == 0) {
        array_push($info['metadata']['artists'], array( "name" => "", "musicbrainz_id" => ""));
    }

    print json_encode($info);

}

function artist_not_found_yet($a) {
    global $foundartists;
    $s = strtolower($a);
    if (in_array($s, $foundartists)) {
        return false;
    } else {
        $foundartists[] = $s;
        return true;
    }
}

function do_composers(&$filedata, &$info) {
    if ($filedata['Composer'] == null) {
        return;
    }
    foreach ($filedata['Composer'] as $comp) {
        if (artist_not_found_yet($comp)) {
            array_push($info['metadata']['artists'], array( "name" => trim($comp), "musicbrainz_id" => "", "type" => "composer", "ignore" => "false"));
        }
    }
}

function do_performers(&$filedata, &$info) {
    if ($filedata['Performer'] == null) {
        return;
    }
    foreach ($filedata['Performer'] as $comp) {
        $toremove = null;
        foreach($info['metadata']['artists'] as $i => $artist) {
            if ($artist['type'] == "albumartist" || $artist['type'] == "artist") {
                if (strtolower($artist['name'] ==  strtolower($comp))) {
                    $toremove = $i;
                    break;
                }
            }
        }
        if ($toremove !== null) {
            array_splice($info['metadata']['artists'], $toremove, 1);
        }

        if ($toremove !== null || artist_not_found_yet($comp)) {
            array_push($info['metadata']['artists'], array( "name" => trim($comp), "musicbrainz_id" => "", "type" => "performer", "ignore" => "false"));
        }
    }
}

function do_albumartist(&$filedata, &$info, $albumartist) {
    $aartist = null;
    if (!($filedata['type'] == "stream" && $albumartist == "Radio") &&
        strtolower($albumartist) != "various artists" &&
        strtolower($albumartist) != "various")
    {
        $aartist = $albumartist;
    }
    if ($aartist !== null && artist_not_found_yet($aartist)) {
        array_push($info['metadata']['artists'], array( "name" => trim($aartist), "musicbrainz_id" => trim($filedata['MUSICBRAINZ_ALBUMARTISTID']), "type" => "albumartist", "ignore" => "false"));
    }
}

function do_track_artists(&$filedata, &$info) {
    if ($filedata['Artist'] == null) {
        return;
    }
    $artists = $filedata['Artist'];
    if (!is_array($artists)) {
        $artists = array($filedata['Artist']);
    }
    $mbids = $filedata['MUSICBRAINZ_ARTISTID'];
    if (count($mbids) > count($artists)) {
        // More MBIDs that Artists. This might be one of those daft things where MBIDs are semicolon-separated
        // but artists are comma-separated.
        // You can even get artists = ['artist1, artist2', 'artist3']. Sigh. Hence the first implode.
        $astring = implode(', ',$artists);
        $newartists = explode(',', $astring);
        if (count($newartists) == count($mbids)) {
            debuglog("GETPLAYLIST","Trying splitting comma-separated artist string",8);
            // In case AlbumArtist has that format too
            artist_not_found_yet($astring);
            $artists = $newartists;
        }
    }
    while (count($mbids) < count($artists)) {
        $mbids[] = "";
    }
    $a = array();
    foreach ($artists as $i => $comp) {
        if ($comp != "") {
            if (artist_not_found_yet($comp)) {
                array_push($info['metadata']['artists'], array( "name" => trim($comp), "musicbrainz_id" => trim($mbids[$i]), "type" => "artist", "ignore" => "false"));
                $a[] = $comp;
            }
        }
    }
    // This is to try and prevent repeated names - eg artists = [Pete, Dud] and albumartist = Pete & Dud or Dud & Pete
    artist_not_found_yet(concatenate_artist_names($a));
    artist_not_found_yet(concatenate_artist_names(array_reverse($a)));
}

?>
