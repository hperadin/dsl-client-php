<?php
use NGS\Converter\XmlConverter;

/**
 * A utility method for comparing two arrays converted by
 * XmlConverter from a SimpleXml tree. The arrays are considered
 * equivalent if the Xml trees are identical up to the ordering of the Xml nodes.
 *
 * The comparator builds a list of all paths root-to-leaf, and for each of
 * those paths in the LHS tree tries to find a match in the RHS tree. If every
 * path is matched, the comparison function returns {@code true}, {@code false}
 * otherwise.
 *
 * Notes:
 *
 * There are problems with namespace attributes. The {@code SimpleXml} library does not
 * handle XML namespace declarations gracefuly. While namespaces are parsed flawlessly,
 * the namespace attributes themselves are not listed together with all the rest of the
 * attributes. XmlConverter improvises a workaround, according to a node's namespace it
 * artificially inserts the xmlns attributes into the topmost node used. As a consequence,
 * any redundant xlmns declarations are lost in the conversion. Thus namespaces are taken
 * into account during comparison, and any duplicate namespace declarations are ignored
 *
 * Problems with the #text nodes. With Xml nodes having only a redundant xmlns declaration
 * and text content the xmlns attribute is lost in conversion, and the #text node is merged
 * into a string.
 */
class XmlJsonArrayComparator {

  /**
   * Returns true if the two XML arrays are equal
   */
  public static function equals($xml_lhs, $xml_rhs) {

    $xmlPaths_lhs = array ();
    $xmlPaths_rhs = array ();

    $pathUpToNode_lhs=array();
    $pathUpToNode_rhs=array();

    self::buildPaths ( $xmlPaths_lhs, $pathUpToNode_lhs, $xml_lhs);
    self::buildPaths ( $xmlPaths_rhs, $pathUpToNode_rhs, $xml_rhs);

    $result = self::compareAllPaths ( $xmlPaths_lhs, $xmlPaths_rhs );

    return $result;
  }

  private static function buildPaths(&$allPaths, $pathUpToCurrentNode, $node) {
    if (is_array ( $node )) {
      foreach ( $node as $key => $child ) {

        $pathUpUntilThisChild = $pathUpToCurrentNode;
        $pathUpUntilThisChild [] = $key;

        self::buildPaths ( $allPaths, $pathUpUntilThisChild, $child);
      }
    } else {
      $pathUpToCurrentNode [] = $node;
      $allPaths [] = $pathUpToCurrentNode;

      //Debug output:
      //print "Path complete -> \n";
      //print "Path up till now:";
      //var_dump ( $pathUpToCurrentNode );
    }


  }

  /**
   * Compares all root-to-leaf paths of the two XML trees.
   *
   * For each path in lhs list find an equivalent path in the rhs list, and
   * remove it if it exists.
   *
   * If for every path in lhs a unique equivalent path in rhs is found, the
   * trees are considered equal.
   *
   * @param
   *          lhs
   *          The lhs XML tree paths (array of arrays)
   * @param
   *          rhs
   *          The rhs XML tree paths (array of arrays)
   * @return {@code true} if they are equal {@code false} otherwise
   */
  private static function compareAllPaths($lhs_path, $rhs_path) {

    $retval=TRUE;

    $lhs=$lhs_path;
    $rhs=$rhs_path;

     if (count ( $lhs ) != count ( $rhs )) {
       print_r ( "The number of root-to-leaf paths is not equal: " . count ( $lhs ) . " vs. " . count ( $rhs ) . "\n" );
       $retval=false;

       if(count($rhs)>count($lhs)){
         print " (reversing lhr/rhs for debugging) \n";
         $lhs=$rhs_path;
         $rhs=$lhs_path;
       }
     }

    foreach ( $lhs as $key_lhs => $lhsPath ) {
      $found = FALSE;
      foreach ( $rhs as $key => $rhsPath ) {
        if (self::nodeListsEqual ( $lhsPath, $rhsPath )) {
          $found = TRUE;
          unset ( $lhs [$key_lhs] );
          unset ( $rhs [$key] );

// Debug output:
//           print_r ( "===\n" );
//           print_r ( "Match!:\n" );
//           print_r ( "===lhs:".count($lhs)."===\n" );
//           var_dump($lhsPath);
//           print_r ( "===rhs:".count($rhs)."===\n" );
//           var_dump($rhsPath);
//           print_r ( "===\n" );

          break;
        }
      }
      if ($found === FALSE) {
//         print_r ( "---\n" );
//         print_r ( "No pair found for path:\n" );
//         print_r ( "---\n" );
//         var_dump ( $lhsPath );
//         print_r ( "---\n" );
        $retval=FALSE;
      }
    }

    return $retval;
  }

  /**
   * Compares two lists of nodes.
   *
   * The lists are considered equal if their nodes are equal. Since they
   * represent paths in a tree, their ordering is relevant.
   *
   * @param
   *          lhs
   *          Array of nodes serialized into arrays
   * @param
   *          rhs
   *          Array of nodes serialized into arrays
   * @return true if the {@code lists} are equal, {@code false} otherwise
   */
  private static function nodeListsEqual($lhs, $rhs) {
    if (count ( $lhs ) != count ( $rhs ))
      return false;

    reset ( $lhs );
    reset ( $rhs );

    while ( current ( $lhs ) !== FALSE ) {
      $node1 = current ( $lhs );
      $node2 = current ( $rhs );

      if (($node1 === $node2) === FALSE){
        return false;
      }

      next ( $lhs );
      next ( $rhs );
    }

    return true;
  }
}
