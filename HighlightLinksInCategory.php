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
        
        $pagesToQuery = array_keys($linkcolour_ids);
        $nonRedirects = array_keys($linkcolour_ids);
        $pageToTargetLookup = [];

        $dbr = wfGetDB( DB_REPLICA );
        
        if ( $wgHighlightLinksInCategoryFollowRedirects ) {
            $res0 = $dbr->select(
                [ 'redirect', 'page' ],
                array('rd_from', 'page_id'),
                $dbr->makeList( [
                    'rd_namespace = page_namespace',
                    'rd_title = page_title',
                    $dbr->makeList(
                        array('rd_from' => $pagesToQuery ), LIST_OR ),
                   // 'rd_interwiki IS NULL',
                ], LIST_AND )
            );
            foreach ( $res0 as $row ) {
                # first, forget this page as a non-redirect
                $nonRedirects = array_diff( $nonRedirects, [$row->rd_from] );
                # and also as a page to query
                $pagesToQuery = array_diff( $pagesToQuery, [$row->rd_from] );
                
                # then make sure we remember this association
                $pageToTargetLookup[$row->rd_from] = $row->page_id;
                # and we also need to query this later
                $pagesToQuery[] = $row->page_id;
            }
            
            # now that nonRedirects is fully populated, tell our lookup about them
            foreach ( $nonRedirects as $nonRedirect ) {
                $pageToTargetLookup[$nonRedirect] = $nonRedirect;
            }
        }

        $catNames = array_keys($wgHighlightLinksInCategory);

        # Get page ids with appropriate categories from the DB
        # There's an index on (cl_from, cl_to) so this should be fast
        
        $res = $dbr->select( 'categorylinks',
            array('cl_from', 'cl_to'),
            $dbr->makeList( array(
                $dbr->makeList(
                    array( 'cl_from' => $pagesToQuery ), LIST_OR),
                $dbr->makeList(
                    array( 'cl_to'   => $catNames), LIST_OR)
                ),
                LIST_AND
            )
        );
        
        $classes = [];
        foreach( $res as $s ) {
            $classes[ $s->cl_from ] = '';
            $classes[ $s->cl_from ] .= ' ' . $wgHighlightLinksInCategory[ $s->cl_to ];
        }
        
        # Add the color classes to each page
        foreach ( $pageToTargetLookup as $page=>$target ) {
            echo( $page );
            echo( ',' );
            echo( $target );
            echo( $classes[ $target ] );
            echo( '<br>' );
            if ( $classes[ $target ] != '' ) {
                $colours[ $page ] = $classes[ $target ];
            }
        }
    }

}

