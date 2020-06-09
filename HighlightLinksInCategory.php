<?php
# Extension: Highlight Links in Category
# Copyright 2013, 2016 Brent Laabs
# Released under a MIT-style license.  See LICENSE for details

# You can probably uncomment this to get it to work under 1.12
#
#$wgExtensionCredits['other'][] = array(
#        'name' => 'Highlight Links in Category',
#        'author' => 'Brent Laabs',
#        'version' => '0.9',
#        'url' => 'https://github.com/labster/mediawiki-highlight-links-in-category',
#        'descriptionmsg' => 'Highlights links in categories via customizable CSS classes',
#);
#
# $wgHooks['GetLinkColours'][] = 'HighlightLinksInCategory::onGetLinkColours';

class HighlightLinksInCategory {

    public static function onGetLinkColours( $linkcolour_ids, &$colours ) {
	global $wgHighlightLinksInCategory;
    global $wgHighlightLinksInCategoryFollowRedirects;

        if ( ! count($wgHighlightLinksInCategory) ) {
            return true;
        }

        # linkcolour_ids only contains pages that exist, which does a lot
        # of our work for us
        # let's follow all redirects if the user wants to
        $targetPageIDs  = [];
        if not ( $wgHighlightLinksInCategoryFollowRedirects ) {
            targetPageIDs = array_keys($linkcolour_ids);
        }
        else {
            foreach ( $linkcolour_ids as $id => $page ) {
                $targetPageIDs[] = $page->isRedirect() ? $page->followRedirect()->getId() : $id;
            }
        }
        $catNames = array_keys($wgHighlightLinksInCategory);

        # Get page ids with appropriate categories from the DB
        # There's an index on (cl_from, cl_to) so this should be fast
        $dbr = wfGetDB( DB_REPLICA );
        $res = $dbr->select( 'categorylinks',
            array('cl_from', 'cl_to'),
            $dbr->makeList( array(
                $dbr->makeList(
                    array( 'cl_from' => $targetPageIDs ), LIST_OR),
                $dbr->makeList(
                    array( 'cl_to'   => $catNames), LIST_OR)
                ),
                LIST_AND
            )
        );

        # Add the color classes to each page
        foreach ( $res as $s ) {
            $colours[ $linkcolour_ids[ $s->cl_from ] ]
                .= ' ' . $wgHighlightLinksInCategory[ $s->cl_to ];
        }
    }

}

