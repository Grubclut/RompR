<?php

require_once('utils/imagefunctions.php');

function albumTrack($data) {
    global $prefs;
    if (substr($data['title'],0,6) == "Album:") return 2;
    if (substr($data['title'],0,7) == "Artist:") return 1;

    $d = getDomain($data['uri']);

    if ($prefs['player_backend'] == "mpd" && $d == "soundcloud") {
        $class = 'clickcue';
    } else {
        $class = 'clicktrack';
    }
    $class .= $data['discclass'];

    // Outer container
    if ($data['uri'] == null) {
        print '<div class="playable '.$class.' ninesix draggable indent containerbox padright calign" name="'.$data['ttid'].'">';
    } else {
        print '<div class="playable '.$class.' ninesix draggable indent containerbox padright calign" name="'.rawurlencode($data['uri']).'">';
    }

    // Track Number
    if ($data['trackno'] && $data['trackno'] != "" && $data['trackno'] > 0) {
        print '<div class="tracknumber fixed"';
        if ($data['numtracks'] > 99 || $data['trackno'] > 99) {
            print ' style="width:3em"';
        }
        print '>'.$data['trackno'].'</div>';
    }

    print domainIcon($d, 'collecionicon');

    // Track Title, Artist, and Rating
    if ((string) $data['title'] == "") $data['title'] = urldecode($data['uri']);
    print '<div class="expand containerbox vertical">';
    print '<div class="fixed tracktitle">'.$data['title'].'</div>';
    if ($data['artist'] && $data['trackartistindex'] != $data['albumartistindex']) {
        print '<div class="fixed playlistrow2 trackartist">'.$data['artist'].'</div>';
    }
    if ($data['rating']) {
        print '<div class="fixed playlistrow2 trackrating">';
        print '<i class="icon-'.trim($data['rating']).'-stars rating-icon-small"></i>';
        print '</div>';
    }
    if ($data['tags']) {
        print '<div class="fixed playlistrow2 tracktags">';
        print '<i class="icon-tags collectionicon"></i>'.$data['tags'];
        print '</div>';
    }
    print '</div>';

    // Track Duration
    print '<div class="fixed playlistrow2 tracktime">';
    if ($data['time'] > 0) {
        print format_time($data['time']);
    }
    print '</div>';

    // Delete Button
    if ($data['lm'] === null) {
        print '<i class="icon-cancel-circled playlisticonr fixed clickable clickicon clickremdb"></i>';
    }

    print '</div>';

    return 0;
}

function artistHeader($id, $name) {
    global $divtype;
    $h = '<div class="menu openmenu containerbox menuitem artist '.$divtype.'" name="'.$id.'">';
    $h .= '<div class="expand">'.$name.'</div>';
    $h .= '</div>';
    return $h;
}

function noAlbumsHeader() {
    print '<div class="playlistrow2" style="padding-left:64px">'.get_int_text("label_noalbums").'</div>';
}

function albumHeader($obj) {
    global $prefs;
    $h = '<div class="collectionitem fixed selecotron clearfix">';
    if ($obj['id'] == 'nodrop') {
        // Hacky at the moment, we only use nodrop for streams but here there is no checking
        // because I'm lazy.
        $h .= '<div class="containerbox wrap clickstream playable clickicon '.$obj['class'].'" name="'.$obj['streamuri'].'" streamname="'.$obj['streamname'].'" streamimg="'.$obj['streamimg'].'">';
    } else {
        if (array_key_exists('plpath', $obj)) {
            $h .= '<input type="hidden" name="dirpath" value="'.$obj['plpath'].'" />';
        }
        $h .= '<div class="containerbox wrap openmenu menu '.$obj['class'].'" name="'.$obj['id'].'">';
    }

    $h .= '<div class="helpfulalbum expand">';
    $albumimage = new baseAlbumImage(array('baseimage' => $obj['Image']));
    $h .= $albumimage->html_for_image($obj, 'jalopy', 'medium');

    $h .= '<div class="tagh albumthing">';
    $h .= '<div class="title-menu">';

    $h .= domainHtml($obj['AlbumUri']);

    $h .= '<div class="artistnamething">'.$obj['Albumname'];
    if ($obj['Year'] && $prefs['sortbydate']) {
        $h .= ' <span class="notbold">('.$obj['Year'].')</span>';
    }
    $h .= '</div>';
    if ($obj['Artistname']) {
        $h.= '<div class="notbold">'.$obj['Artistname'].'</div>';
    }
    $h .= '</div>';
    $h .= '</div>';

    $h .= '</div>';
    $h .= '</div>';
    $h .= '</div>';

    return $h;
}

function albumControlHeader($fragment, $why, $what, $who, $artist) {
    if ($fragment || $who == 'root') {
        return '';
    }
    $html = '<div class="configtitle textcentre tagholder_wide brick"><b>'.$artist.'</b></div>';
    $html .= '<div class="textcentre clickalbum playable ninesix tagholder_wide brick noselect" name="'.$why.'artist'.$who.'">'.get_int_text('label_play_all').'</div>';
    return $html;
}

function trackControlHeader($why, $what, $who, $dets) {
    $html = '';
    foreach ($dets as $det) {
        if ($why != '') {
            $html .= '<div class="containerbox wrap album-play-controls">';
            if ($det['AlbumUri']) {
                $albumuri = rawurlencode($det['AlbumUri']);
                if (strtolower(pathinfo($albumuri, PATHINFO_EXTENSION)) == "cue") {
                    $html .= '<div class="icon-no-response-playbutton smallicon expand playable clickcue noselect" name="'.$albumuri.'"></div>';
                } else {
                    $html .= '<div class="icon-no-response-playbutton smallicon expand clicktrack playable noselect" name="'.$albumuri.'"></div>';
                    $html .= '<div class="icon-music smallicon expand clickalbum playable noselect" name="'.$why.'album'.$who.'"></div>';
                }
            } else {
                $html .= '<div class="icon-no-response-playbutton smallicon expand clickalbum playable noselect" name="'.$why.'album'.$who.'"></div>';
            }
            $html .= '<div class="icon-single-star smallicon expand clickicon clickalbum playable noselect" name="ralbum'.$who.'"></div>';
            $html .= '<div class="icon-tags smallicon expand clickicon clickalbum playable noselect" name="talbum'.$who.'"></div>';
            $html .= '<div class="icon-ratandtag smallicon expand clickicon clickalbum playable noselect" name="yalbum'.$who.'"></div>';
            $html .= '<div class="icon-ratortag smallicon expand clickicon clickalbum playable noselect" name="ualbum'.$who.'"></div>';
            $html .= '</div>';
            $html .= '<div class="textcentre ninesix playlistrow2">'.ucfirst(get_int_text('label_tracks')).'</div>';
        }
    }
    print $html;
}

function printDirectoryItem($fullpath, $displayname, $prefix, $dircount, $printcontainer = false) {
    $c = ($printcontainer) ? "searchdir" : "directory";
    print '<input type="hidden" name="dirpath" value="'.rawurlencode($fullpath).'" />';
    print '<div class="'.$c.' menu openmenu containerbox menuitem brick_wide" name="'.$prefix.$dircount.'">';
    print '<i class="icon-folder-open-empty fixed collectionicon"></i>';
    print '<div class="expand">'.htmlentities(urldecode($displayname)).'</div>';
    print '</div>';
    if ($printcontainer) {
        print '<div class="dropmenu" id="'.$prefix.$dircount.'">';
    }
}

function directoryControlHeader($prefix, $name = null) {
    if ($name !== null) {
        print '<div class="menuitem configtitle textcentre brick_wide"><b>'.$name.'</b></div>';
    }
}

function printRadioDirectory($att) {
    $name = md5($att['URL']);
    print '<input type="hidden" value="'.rawurlencode($att['URL']).'" />';
    print '<input type="hidden" value="'.rawurlencode($att['text']).'" />';
    print '<div class="browse menu clickable tunein directory containerbox menuitem brick_wide" name="tunein_'.$name.'">';
    print '<i class="icon-folder-open-empty fixed collectionicon"></i>';
    print '<div class="expand">'.$att['text'].'</div>';
    print '</div>';
    print '<div id="tunein_'.$name.'" class="invisible indent containerbox wrap fullwidth"></div>';
}

function playlistPlayHeader($name) {
    print '<div class="textcentre clickloadplaylist playable ninesix" name="'.$name.'">'.get_int_text('label_play_all');
    print '<input type="hidden" name="dirpath" value="'.$name.'" />';
    print '</div>';
}

function addPodcastCounts($html, $extra) {
    $out = phpQuery::newDocument($html);
    $out->find('.containerbox.wrap')->removeClass('wrap')->addClass('vertical');
    $extra = '<div class="helpfulalbum fixed podcastcounts">'.$extra.'</div>';
    $out->find('.containerbox.vertical')->append($extra);
    return $out;
}

function addUserRadioButtons($html, $index, $uri, $name, $image) {
    $out = phpQuery::newDocument($html);
    $extra = '<div class="fixed containerbox">';
    $extra .= '<div class="expand"></div>';
    $extra .= '<i class="clickable clickradioremove clickicon icon-cancel-circled collectionicon yourradio" name="'.$index.'"></i>';
    $extra .= "</div>";
    $out->find('.helpfulalbum')->append($extra);
    return $out;
}

function addPlaylistControls($html, $delete, $is_user, $name) {
    global $prefs;
    $out = phpQuery::newDocument($html);
    if ($delete && ($is_user || $prefs['player_backend'] == "mpd")) {
        $add = ($is_user) ? "user" : "";
        $h = '<div class="fixed containerbox">';
        $h .= '<i class="icon-floppy fixed smallicon clickable clickicon clickrename'.$add.'playlist"></i>';
        $h .= '<input type="hidden" value="'.$name.'" />';
        $h .= '<div class="expand"></div>';
        $h .= '<i class="icon-cancel-circled fixed smallicon clickable clickicon clickdelete'.$add.'playlist"></i>';
        $h .= '<input type="hidden" value="'.$name.'" />';
        $h .= '</div>';
        $out->find('.helpfulalbum')->append($h);
    }
    return $out;
}

?>
