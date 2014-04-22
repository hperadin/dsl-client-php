<?php
use NGS\Converter\XmlConverter;

/**
 * TODO: Not a test
 * TODO: Docs
 * Utility method for comparing two Xml arrays
 */
class XmlArrayComparator {

  /**
   * Returns true if the two XML arrays are equal
   */
  public static function equals($xml_lhs, $xml_rhs) {
    // TODO: normalize namespace attributes, currently there are problems with those
    // TODO: perhaps collapse text and cdata nodes for comparison
    $xmlPaths_lhs = array ();
    $xmlPaths_rhs = array ();

    $pathUpToNode_lhs=array();
    $pathUpToNode_rhs=array();

    print_r("------------LHS--------------\n");
    self::buildPaths ( $xmlPaths_lhs, $pathUpToNode_lhs, $xml_lhs);
    print_r("-------------RHS-------------\n");
    self::buildPaths ( $xmlPaths_rhs, $pathUpToNode_rhs, $xml_rhs);
    print_r("--------------------------\n");

    $result = self::compareAllPaths ( $xmlPaths_lhs, $xmlPaths_rhs );
    var_dump ( $result );

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

      print "Path complete -> \n";
      print "Path up till now:";
      var_dump ( $pathUpToCurrentNode );
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
  private static function compareAllPaths($lhs, $rhs) {

    $retval=TRUE;

     if (count ( $lhs ) != count ( $rhs )) {
       print_r ( "The number of root-to-leaf paths is not equal: " . count ( $lhs ) . " vs. " . count ( $rhs ) . "\n" );
       //return false;
       $retval=false;
     }



    foreach ( $lhs as $key_lhs => $lhsPath ) {
      $found = FALSE;
      foreach ( $rhs as $key => $rhsPath ) {
        if (self::nodeListsEqual ( $lhsPath, $rhsPath )) {
          $found = TRUE;
          unset ( $lhs [$key_lhs] );
          unset ( $rhs [$key] );

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
        print_r ( "---\n" );
        print_r ( "No pair found for path:\n" );
        print_r ( "---\n" );
        var_dump ( $lhsPath );
        $retval=FALSE;
      }
    }

    //return true;
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
      // if(self::nodesEqual($node1, $node2)===FALSE)
      if (($node1 === $node2) === FALSE){
        return false;
      }

      next ( $lhs );
      next ( $rhs );
    }

    return true;
  }
  private static function nodesEqual($node1, $node2) {
    if (! isset ( $node1 ) & ! isset ( $node2 ))
      return true;
    else if (! isset ( $node1 ) || ! isset ( $node2 )) {
      return false;
    } else {
      return self::nodesHaveEqualNames ( $node1, $node2 ) && self::nodesHaveEqualNumberOfChildren ( $node1, $node2 ) && self::nodesHaveEqualValues ( $node1, $node2 );
    }
  }
  private static function nodesHaveEqualNames($node1, $node2) {
    return key ( $node1 ) === key ( $node2 );
  }
  private static function nodesHaveEqualNumberOfChildren($node1, $node2) {
    return count ( $node1 ) === count ( $node2 );
  }
  private static function nodesHaveEqualValues($node1, $node2) {
    return value ( $node1 ) === value ( $node2 );
  }
}
