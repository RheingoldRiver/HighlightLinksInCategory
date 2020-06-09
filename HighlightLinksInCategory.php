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
        $pagesToQuery = array_keys($linkcolour_ids);
        
        # intermediate value that will help us construct pageToTargets
        $nonRedirects = array_keys($linkcolour_ids);
        
        # associative array of page -> target. will be page -> page if
        # link is not a redirect or if user does not want redirects followed
        $pageToTargets = [];

        $dbr = wfGetDB( DB_REPLICA );
        
        # follow all redirects only if the user wants to
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
                $pageToTargets[$row->rd_from] = $row->page_id;
                # and we also need to query the target id later
                $pagesToQuery[] = $row->page_id;
            }
            
        }
        
        # now that nonRedirects is fully populated, tell our lookup about them
        # we can do this regardless of whether we wanted to follow redirects or not
        # if we didn't follow redirects, this is the trivial operation of moving all
        # pages here
        foreach ( $nonRedirects as $nonRedirect ) {
            $pageToTargets[$nonRedirect] = $nonRedirect;
        }

        $catNames = array_keys($wgHighlightLinksInCategory);

        # Get page ids with appropriate categories from the DB
        # There's an index on (cl_from, cl_to) so this should be fast
        
        $resultCL = $dbr->select( 'categorylinks',
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
        foreach( $resultCL as $s ) {
            if ( ! array_key_exists( $s->cl_from, $classes ) ) {
                $classes[ $s->cl_from ] = '';
            }
            $classes[ $s->cl_from ] .= ' ' . $wgHighlightLinksInCategory[ $s->cl_to ];
        }
        
        # Add the color classes to each page
        foreach ( $pageToTargets as $page=>$target ) {
            if ( array_key_exists( $target, $classes ) ) {
                $colours[ $linkcolour_ids[$page] ] = $classes[ $target ];
            }
        }
    }

}
