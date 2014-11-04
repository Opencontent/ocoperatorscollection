<?php

class OCOperatorsCollectionsTools
{
    
    /**
     *
     * http://maps.googleapis.com/maps/api/staticmap?zoom=13&size=600x300&maptype=roadmap&markers=color:blue|{first_set( $attribute.content.latitude, '0.0')},{first_set( $attribute.content.longitude, '0.0')}

     * @param array $parameters
     * @param eZContentObjectAttribute $attribute
     * @return string
     */
    public static function gmapStaticImage( array $parameters, eZContentObjectAttribute $attribute, $extraCacheFileNameStrings = array() )
    {        
        $cacheParameters = array( serialize( $parameters ) );
        
        $cacheFile = $attribute->attribute( 'id' ) . implode( '-', $extraCacheFileNameStrings ) . '-' . md5( implode( '-', $cacheParameters ) ) . '.cache';        
        $extraPath = eZDir::filenamePath( $attribute->attribute( 'id' ) );
        $cacheDir = eZDir::path( array( eZSys::cacheDirectory(), 'gmap_static', $extraPath ) );
        
        // cacenllo altri file con il medesimo attributo
        $fileList = array();
        $deleteItems = eZDir::recursiveList( $cacheDir, $cacheDir, $fileList );
        foreach( $fileList as $file )
        {
            if ( $file['type'] == 'file' && $file['name'] !== $cacheFile )
            {
                unlink( $file['path'] . '/' . $file['name'] );
            }
        }
        
        $cachePath = eZDir::path( array( $cacheDir, $cacheFile ) );
        $args = compact( 'parameters', 'attribute' );
        $cacheFile = eZClusterFileHandler::instance( $cachePath );
        $result = $cacheFile->processCache( array( 'OCOperatorsCollectionsTools', 'gmapStaticImageRetrieve' ),
                                            array( 'OCOperatorsCollectionsTools', 'gmapStaticImageGenerate' ),
                                            null,
                                            null,
                                            $args );
        return $result;
    }
    
    public static function gmapStaticImageGenerate( $file, $args )
    {        
        extract( $args );
        $data = self::gmapStaticImageGetData( $args );        
        return array(
            'scope' => $attribute->attribute( 'data_type_string' ),
            'binarydata' => $data
        );
    }
        
    public static function gmapStaticImageRetrieve( $file, $mtime, $args )
    {        
        if ( !eZContentObject::isCacheExpired( $mtime ) )
        {
            return file_get_contents( $file );
        }
        else
        {
            $expiryReason = 'Content cache is expired';
            return new eZClusterFileFailure( 1, $expiryReason );
        }
    }
    
    protected static function gmapStaticImageGetData( $args )
    {
        extract( $args );
        $markers = array();
        $query = array();
        foreach( $parameters as $key => $value )
        {
            if ( is_array( $value ) )
            {
                foreach( $value as $markerProperties )
                {
                    $latLngArray = array();
                    $markerQuery = array();
                    $markerPositions = array();
                    foreach( $markerProperties as $markerPropertyKey => $markerPropertyValue )
                    {
                        if ( $markerPropertyKey == '_positions' )
                        {
                            foreach( $markerPropertyValue as $position )
                            {
                                if ( $position['lat'] > 0 && $position['lng'] > 0 )
                                {
                                    $markerPositions[] = "{$position['lat']},{$position['lng']}";
                                }
                            }
                        }
                        else
                        {
                            $markerQuery[] = "$markerPropertyKey:$markerPropertyValue";
                        }
                    }
                    if ( empty( $markerPositions ) )
                    {
                        throw new Exception( "Positions not found in parameters " . var_export( $parameters, 1 ) );
                    }
                    else
                    {
                        //markers=color:blue|46.067618,11.117315
                        $query[] = "markers=" . implode( '|', $markerQuery ) . '|' . implode( '|', $markerPositions );
                    }
                }                
            }
            else
            {
                //zoom=13 size=600x300 maptype=roadmap
                $query[] = "$key=$value";
            }
        }
        
        $stringQuery = implode( '&', $query );
        $baseUrl = 'http://maps.googleapis.com/maps/api/staticmap';
        $url = "$baseUrl?$stringQuery";
        $data = eZHTTPTool::getDataByURL( $url );
        eZDebug::writeNotice( "Generate static map for attribute {$attribute->attribute( 'id' )}: $url", __METHOD__ );
        return 'data:image/PNG;base64,' . base64_encode( $data );
    }
    
    /**
     * @param eZContentObject $object
     * @param bool $allVersions
     * @param int $newParentNodeID
     * @throws Exception
     * @return eZContentObject
     */
    public static function copyObject( eZContentObject $object, $allVersions = false, $newParentNodeID = null )
    {
        if ( !$object instanceof eZContentObject )
            throw new InvalidArgumentException( 'Object not found' );
        
        if ( !$newParentNodeID )
            $newParentNodeID = $object->attribute( 'main_parent_node_id' );
            
    
        // check if we can create node under the specified parent node
        if( ( $newParentNode = eZContentObjectTreeNode::fetch( $newParentNodeID ) ) === null )
            throw new InvalidArgumentException( 'Parent node not found' );
    
        $classID = $object->attribute('contentclass_id');
    
        if ( !$newParentNode->checkAccess( 'create', $classID ) )
        {
            $objectID = $object->attribute( 'id' );
            eZDebug::writeError( "Cannot copy object $objectID to node $newParentNodeID, " .
                                   "the current user does not have create permission for class ID $classID",
                                 'content/copy' );
            throw new Exception( 'Object not found' );
        }
    
        $db = eZDB::instance();
        $db->begin();
        $newObject = $object->copy( $allVersions );
        // We should reset section that will be updated in updateSectionID().
        // If sectionID is 0 then the object has been newly created
        $newObject->setAttribute( 'section_id', 0 );
        $newObject->store();
    
        $curVersion        = $newObject->attribute( 'current_version' );
        $curVersionObject  = $newObject->attribute( 'current' );
        $newObjAssignments = $curVersionObject->attribute( 'node_assignments' );
        unset( $curVersionObject );
    
        // remove old node assignments
        foreach( $newObjAssignments as $assignment )
        {
            /** @var eZNodeAssignment $assignment */
            $assignment->purge();
        }
    
        // and create a new one
        $nodeAssignment = eZNodeAssignment::create( array(
                                                         'contentobject_id' => $newObject->attribute( 'id' ),
                                                         'contentobject_version' => $curVersion,
                                                         'parent_node' => $newParentNodeID,
                                                         'is_main' => 1
                                                         ) );
        $nodeAssignment->store();
    
        $db->commit();
        return $newObject;
    }
}