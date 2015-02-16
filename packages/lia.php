<?php
/**
 * [Platform] exporter tool.
 *
 * @copyright Vanilla Forums Inc. 2010-2014
 * @license GNU GPL2
 * @package VanillaPorter
 * @see functions.commandline.php for command line usage.
 */

// Add to the $Supported array so it appears in the dropdown menu. Uncomment next line.
$Supported['Lithium'] = array('name'=> 'Lithium 14.*', 'prefix'=>'');

class Lithium extends ExportController {
   /**
    * You can use this to require certain tables and columns be present.
    *
    * This can be useful for verifying data integrity. Don't specify more columns
    * than your porter actually requires to avoid forwards-compatibility issues.
    *
    * @var array Required tables => columns
    */
   protected $SourceTables = array(
      'users' => array('id', 'nlogin', 'email', 'registration_time', 'last_visit_time', 
          'deleted'), // Require specific cols on 'users'
      'roles' => array('id', 'name', 'deleted'), // This just requires the 'forum' table without caring about columns.
      'user_role' => array('role_id', 'user_id'),
      'nodes' => array('node_id', 'parent_node_id', 'depth', 'display_id', 'position', 
          'owner_user_id', 'created_time', 'deleted'),
      'message2' => array('unique_id', 'user_id', 'node_id', 'subject', 'body', 'views', 
          'post_date', 'edit_date', 'edit_user', 'root_id', 'deleted'),
   );

   /**
    * Main export process.
    *
    * @param ExportModel $Ex
    * @see $_Structures in ExportModel for allowed destination tables & columns.
    */
   public function ForumExport($Ex) {
      // Get the characterset for the comments.
      // Usually the comments table is the best target for this.
      $CharacterSet = $Ex->GetCharacterSet('message2');
      if ($CharacterSet)
         $Ex->CharacterSet = $CharacterSet;

      // Reiterate the platform name here to be included in the porter file header.
      $Ex->BeginExport('', 'Lithium 14.*');

      $exportUsers          = true;
      $exportRoles          = true;
      $exportUserRoles      = true;
      $exportPermissions    = true;
      $exportUserMeta       = false; // TODO: select distinct(param) from user_profile;
      $exportRanks          = true;
      $exportNodes          = true;
      $exportDiscussions    = true;
      $exportTags           = true;
      $exportComments       = true;
      $exportConversations  = false; // TODO wat
      $exportSSO            = true;
      $exportKudos          = true;

      // It's usually a good idea to do the porting in the approximate order laid out here.



      if ($exportUsers) {
        // User.
        // Map as much as possible using the $x_Map array for clarity.
        // Key is always the source column name.
        // Value is either the destination column or an array of meta data, usually Column & Filter.
        // If it's a meta array, 'Column' is the destination column name and 'Filter' is a method name to run it thru.
        // Here, 'HTMLDecoder' is a method in ExportModel. Check there for available filters.
        // Assume no filter is needed and only use one if you encounter issues.
        $User_Map = array(
            'id' => 'UserID',
            'pwd_hash' => 'Password',
            'nlogin' => array('Column' => 'Name', 'Filter' => array($Ex, 'HTMLDecoder')),
            //'id' => 'Photo',
            'About' => 'About',
            'email' => 'Email',
            'ShowEmail' => 'ShowEmail',
            //'id' => 'Gender',
            //'id' => 'CountVisits',
            'Birthday' => 'DateOfBirth',
            'RegistrationDate' => 'DateFirstVisit',
            'LastVisitDate' => 'DateLastActive',
            //'id' => 'LastIPAddress',
            'RegistrationDate' => 'DateInserted',
            'admin' => 'Admin',
            'Verified' => 'Verified',
            'Banned' => 'Banned',
            'deleted' => 'Deleted',
            //'id' => 'CountComments',
            'ranking_id' => 'RankID',
            );
        // This is the query that the x_Map array above will be mapped against.
        // Therefore, our select statement must cover all the "source" columns.
        // It's frequently necessary to add joins, where clauses, and more to get the data we want.
        // The :_ before the table name is the placeholder for the prefix designated. It gets swapped on the fly.
        $Ex->ExportTable('User', "
           SELECT u.*, 
              FROM_UNIXTIME(u.registration_time/1000, '%Y-%m-%d %H:%i:%s') as RegistrationDate,
              FROM_UNIXTIME(u.last_visit_time/1000, '%Y-%m-%d %H:%i:%s') as LastVisitDate,
              (ur.role_id IS NOT NULL) as isAdmin,
              (ban.id IS NOT NULL) as Banned,
              ver.nvalue AS Verified,
              bd.nvalue as Birthday,
              bio.nvalue as About,
              pe.nvalue IS NULL as ShowEmail
           FROM users u
           LEFT OUTER JOIN user_profile ver
              ON u.id = ver.user_id AND ver.param = 'user.email_verified'
           LEFT OUTER JOIN user_bans ban
              ON u.id = ban.user_id
           LEFT OUTER JOIN user_profile bd
              ON u.id = bd.user_id AND bd.param = 'profile.birthday'
           LEFT OUTER JOIN user_profile bio
              ON u.id = bio.user_id AND bio.param = 'profile.biography'
           LEFT OUTER JOIN user_profile pe
              ON u.id = pe.user_id AND pe.param = 'profile.privacy_email'
           LEFT OUTER JOIN user_role ur
              ON u.id = ur.user_id AND ur.role_id = 1
           ", $User_Map);
      }




      if ($exportRanks) {
        $Rank_Map = array(
            'id' => 'RankID',
            'sort_order' => 'Sort',
            'metric_posts' => 'PostReq',
            'average_message_rating' => 'PointReq',
            'registration_age' => 'AgeReq',
            'rank_name' => 'Name',
            'Enabled' => 'Enabled',
        );
        $Ex->ExportTable('Rank', "
           SELECT *,
              if(deleted is not null, not deleted, 0) as Enabled
           FROM user_rankings
           ", $Rank_Map);
      }




      if ($exportRoles) {
        // Role.
        // The Vanilla roles table will be wiped by any import. If your current platform doesn't have roles,
        // you can hard code new ones into the select statement. See Vanilla's defaults for a good example.
        $Role_Map = array(
            'id' => 'RoleID',
            'name' => 'Name', // We let these arrays end with a comma to prevent typos later as we add.
        );
        $Ex->ExportTable('Role', "
           SELECT *
           FROM :_roles
           WHERE deleted = 0 AND node_id = 1", $Role_Map);
      }
      



      if ($exportUserRoles) {
        // User Role.
        // Really simple matchup.
        // Note that setting Admin=1 on the User table trumps all roles & permissions with "owner" privileges.
        // Whatever account you select during the import will get the Admin=1 flag to prevent permissions issues.
        $UserRole_Map = array(
            'user_id' => 'UserID',
            'role_id' => 'RoleID',
        );
        $Ex->ExportTable('UserRole', "
           SELECT u.*
           FROM :_user_role u
           JOIN roles r ON r.id = u.role_id
           WHERE r.deleted = 0 AND r.node_id = 1", $UserRole_Map);
      }
      



      if ($exportPermissions) {
        // Permission.
        // Feel free to add a permission export if this is a major platform or it will see reuse.
        // For small or custom jobs, it's usually not worth it. Just fix them afterward.

        $Permission_Map = array(
            // misc
            'RoleID' => 'RoleID',
            'JunctionTable' => 'JunctionTable',
            'JunctionColumn' => 'JunctionColumn',
            'JunctionID' => 'JunctionID',
            'view_personal_info' => 'Garden.PersonalInfo.View', // ***
            // Garden
            'ReadMessage' => 'Garden.AdvancedNotifications.Allow', // ***
            'DeleteMessage' => 'Garden.Activity.Delete', // ***
            'ReadMessage' => 'Garden.Activity.View',
            'view_personal_info' => 'Garden.Email.View',
            'manage_messages' => 'Garden.Messages.Manage',
            'modbar_manage' => 'Garden.Moderation.Manage',
            'ManageUsers' => 'Garden.Profiles.Edit',
            'MyTrueField' => 'Garden.Profiles.View',
            'ManageSettings' => 'Garden.Settings.Manage', // ***
            'ManageSettings' => 'Garden.Settings.View',
            'MyTrueField' => 'Garden.SignIn.Allow',
            'ManageUsers' => 'Garden.Users.Add', // ***
            'ManageUsers' => 'Garden.Users.Approve', // ***
            'ManageUsers' => 'Garden.Users.Delete', // ***
            'ManageUsers' => 'Garden.Users.Edit', // ***
            // Conversations
            'create_thread' => 'Conversations.Conversations.Add',
            'access_moderation_manager' => 'Conversations.Moderation.Manage',
            // Vanilla
            'MyFalseField' => 'Vanilla.Approval.Require',
            'ManageUsers' => 'Vanilla.Comments.Me',
            // Default Category Permissions
            'create_message' => 'Vanilla.Comments.Add',
            'DeleteMessage' => 'Vanilla.Comments.Delete',
            'update_message' => 'Vanilla.Comments.Edit',
            'create_thread' => 'Vanilla.Discussions.Add',
            'allow_float_for_all_users' => 'Vanilla.Discussions.Announce', // ***
            'DeleteMessage' => 'Vanilla.Discussions.Close', // ***
            'DeleteMessage' => 'Vanilla.Discussions.Delete', // ***
            'update_message' => 'Vanilla.Discussions.Edit', // ***
            'allow_float_for_all_users' => 'Vanilla.Discussions.Sink',
            'ReadMessage' => 'Vanilla.Discussions.View'
        );
        $Ex->ExportTable('Permission', "
           SELECT 
              1 as MyTrueField,
              0 as MyFalseField,
              r.id as RoleID,
              'Category' as JunctionTable,
              'PermissionCategoryID' as JunctionColumn,
              -1 as JunctionID,
              if (vpi.access_level is not null, vpi.access_level > 0, 0) as view_personal_info,
              if (rm.access_level is not null, rm.access_level > 0, 0) as ReadMessage,
              if (dm.access_level is not null, dm.access_level > 0, 0) as DeleteMessage,
              if (mm.access_level is not null, mm.access_level > 0, 0) as manage_messages,
              if (mMod.access_level is not null, mMod.access_level > 0, 0) as modbar_manage,
              if (mu.access_level is not null, mu.access_level > 0, 0) as ManageUsers,
              if (cc.access_level is not null, cc.access_level > 0, 0) as ManageSettings,
              if (ct.access_level is not null, ct.access_level > 0, 0) as create_thread,
              if (amm.access_level is not null, amm.access_level > 0, 0) as access_moderation_manager,
              if (cm.access_level is not null, cm.access_level > 0, 0) as create_message,
              if (um.access_level is not null, um.access_level > 0, 0) as update_message,
              if (f.access_level is not null, f.access_level > 0, 0) as allow_float_for_all_users
           FROM roles r
           LEFT OUTER JOIN role_permission vpi
              ON vpi.role_id = r.id and vpi.permission = 'view_personal_info'
           LEFT OUTER JOIN role_permission rm
              ON rm.role_id = r.id and rm.permission = 'read_message'
           LEFT OUTER JOIN role_permission dm
              ON dm.role_id = r.id and dm.permission = 'delete_message'
           LEFT OUTER JOIN role_permission mm
              ON mm.role_id = r.id and mm.permission = 'manage_messages'
           LEFT OUTER JOIN role_permission mMod
              ON mMod.role_id = r.id and mMod.permission = 'modbar_manage'
           LEFT OUTER JOIN role_permission mu
              ON mu.role_id = r.id and mu.permission = 'allow_manage_users'
           LEFT OUTER JOIN role_permission cc
              ON cc.role_id = r.id and cc.permission = 'create_category'
           LEFT OUTER JOIN role_permission ct
              ON ct.role_id = r.id and ct.permission = 'create_thread'
           LEFT OUTER JOIN role_permission amm
              ON amm.role_id = r.id and amm.permission = 'access_moderation_manager'
           LEFT OUTER JOIN role_permission cm
              ON cm.role_id = r.id and cm.permission = 'create_message'
           LEFT OUTER JOIN role_permission um
              ON um.role_id = r.id and um.permission = 'update_message'
           LEFT OUTER JOIN role_permission f
              ON f.role_id = r.id and f.permission = 'allow_float_for_all_users'
           WHERE r.deleted = 0 AND r.node_id = 1", $Permission_Map);
      }




      if ($exportUserMeta) {
        // UserMeta.
        // This is an example of pulling Signatures into Vanilla's UserMeta table.
        // This is often a good place for any extraneous data on the User table too.
        // The Profile Extender addon uses the namespace "Profile.[FieldName]"
        // You can add the appropriately-named fields after the migration and profiles will auto-populate with the migrated data.

        $UserMeta_Map = array(
            'user_id' => 'UserID',
            'Signature' => 'signature',
        );
        $Ex->ExportTable('UserMeta', "
           select
              Author_ID as UserID,
              'Plugin.Signatures.Sig' as `Name`,
              Signature as `Value`
           from :_tblAuthor
           where Signature <> ''");
      }




      if ($exportNodes) {
        // Category.
        // Be careful to not import hundreds of categories. Try translating huge schemas to Tags instead.
        // Numeric category slugs aren't allowed in Vanilla, so be careful to sidestep those.
        // Don't worry about rebuilding the TreeLeft & TreeRight properties. Vanilla can fix this afterward
        // if you just get the Sort and ParentIDs correct.
        $Category_Map = array(
            'node_id' => 'CategoryID',
            'parent_node_id' => 'ParentCategoryID',
            'DepthCalc' => 'Depth',
            //'' => 'CountDiscussions',
            //'' => 'CountComments',
            'paths' => 'Name',
            'display_id' => 'UrlCode',
            //'' => 'Description',
            'position' => 'Sort',
            //'' => 'PermissionCategoryID',
            'DisplayAs' => 'DisplayAs',
            'owner_user_id' => 'InsertUserID',
            'owner_user_id' => 'UpdateUserID',
            'CreatedDate' => 'DateInserted',
            'CreatedDate' => 'DateUpdated',
        );
        $Ex->ExportTable('Category', "
           SELECT c.*, 
              (c.depth - 2) as DepthCalc,
              IF(c.type_id = 2,'Categories','Discussions') as DisplayAs,
              FROM_UNIXTIME(c.created_time/1000, '%Y-%m-%d %H:%i:%s') as CreatedDate
           FROM nodes c
           WHERE c.deleted = 0 and depth > 2 and c.node_id NOT IN (72, 73)
           ", $Category_Map);
      }




      if ($exportDiscussions) {
        // Discussion.
        // A frequent issue is for the OPs content to be on the comment/post table, so you may need to join it.
        $Discussion_Map = array(
            'unique_id' => 'DiscussionID',
            //'' => 'Type'
            'node_id' => 'CategoryID',
            'user_id' => 'InsertUserID',
            'FirstCommentID' => 'FirstCommentID',
            'LastCommentID' => 'LastCommentID',
            'subject' => array('Column' => 'Name', 'Filter' => array($Ex, 'HTMLDecoder')),
            'body' => array('Column' => 'Body', 'Filter' => array($Ex, 'HTMLDecoder')),
            //'' => 'Format',
            'Tags' => 'Tags',
            'CountComments' => 'CountComments',
            'views' => 'CountViews',
            'DateInserted' => 'DateInserted',
            'DateLastComment' => 'DateLastComment',
            'LastCommentUserID' => 'LastCommentUserID',
            'DateUpdated' => 'DateUpdated',
            'edit_user' => 'UpdateUserID',
            );
        // It's easier to convert between Unix time and MySQL datestamps during the db query.
        $Ex->ExportTable('Discussion', "
           SELECT m.*,
              FROM_UNIXTIME(post_date/1000, '%Y-%m-%d %H:%i:%s') as DateInserted,
              FROM_UNIXTIME(edit_date/1000, '%Y-%m-%d %H:%i:%s') as DateUpdated,
              (SELECT GROUP_CONCAT(t.tag_text) FROM tags t JOIN tag_events_message e ON t.tag_id = e.tag_id WHERE e.target_id = m.unique_id) AS Tags,
              (SELECT fcm2.unique_id FROM message2 fcm2 where fcm2.root_id = m.id and fcm2.node_id = m.node_id order by fcm2.post_date ASC limit 1) as FirstCommentID,
              (SELECT lcm2.unique_id FROM message2 lcm2 where lcm2.root_id = m.id and lcm2.node_id = m.node_id order by lcm2.post_date DESC limit 1) as LastCommentID,
              (SELECT count(*) FROM message2 cc WHERE cc.root_id = m.id AND cc.node_id = m.node_id) as CountComments,
              FROM_UNIXTIME((SELECT lcd.post_date FROM message2 lcd WHERE lcd.unique_id = LastCommentID)/1000, '%Y-%m-%d %H:%i:%s') as DateLastComment,
              (SELECT lcu.user_id FROM message2 lcu WHERE lcu.unique_id = LastCommentID) as LastCommentUserID
           FROM message2 m
           WHERE m.root_id = m.id AND m.deleted = 0 and node_id NOT IN (72,73)", $Discussion_Map);
      }




      if ($exportTags) {
        // Model
        $Tag_Map = array(
            'tag_id' => 'TagID',
            'tag_text_canon' => 'Name',
            'EmptyString' => 'Type',
            'DateInserted' => 'DateInserted',
            'NegativeOne' => 'CategoryID',
            'CountDiscussions' => 'CountDiscussions',
        );
        $Ex->ExportTable('Tag', "
           SELECT t.*,
              FROM_UNIXTIME(creation_time/1000, '%Y-%m-%d %H:%i:%s') as DateInserted,
              '' as EmptyString,
              -1 as NegativeOne,
              (SELECT count(*) FROM tag_events_message tem where tem.tag_id = t.tag_id) as CountDiscussions
           FROM tags t
           WHERE  (SELECT count(*) FROM tag_events_message tem WHERE tem.tag_id = t.tag_id) >= 5
           ", $Tag_Map);

        // Associative table
        $TagDiscussion_Map = array(
            'tag_id' => 'TagID',
            'target_id' => 'DiscussionID',
            'CategoryID' => 'CategoryID',
            'DateInserted' => 'DateInserted',
        );
        $Ex->ExportTable('TagDiscussion', "
           SELECT t.*,
              m2.node_id as CategoryID
           FROM tag_events_message t
           JOIN message2 m2 on m2.unique_id = t.target_id
           WHERE  (SELECT count(*) FROM tag_events_message tem WHERE tem.tag_id = t.tag_id) >= 5
           ", $TagDiscussion_Map);
      }




      if ($exportComments) {
        // Comment.
        // This is where big migrations are going to get bogged down.
        // Be sure you have indexes created for any columns you are joining on.
        $Comment_Map = array(
            'unique_id' => 'CommentID',
            'RootID' => 'DiscussionID', // TODO!!!
            'user_id' => 'InsertUserID',
            'edit_user' => 'UpdateUserID',
            'body' => array('Column' => 'Body'),
            //'Format' => 'Format',
            'DateInserted' => 'DateInserted',
            'DateUpdated' => 'DateUpdated',
        );
        $Ex->ExportTable('Comment', "
          SELECT m.*,
              FROM_UNIXTIME(m.post_date/1000, '%Y-%m-%d %H:%i:%s') as DateInserted,
              FROM_UNIXTIME(m.edit_date/1000, '%Y-%m-%d %H:%i:%s') as DateUpdated,
              r.unique_id as RootID
          FROM message2 m
          JOIN message2 r ON m.root_id = r.id AND m.node_id = r.node_id
          WHERE m.id != m.root_id AND m.deleted = 0 and m.node_id NOT IN (72, 73)", $Comment_Map);
      }


      // UserDiscussion.
      // This is the table for assigning bookmarks/subscribed threads.


      // Media.
      // Attachment data goes here. Vanilla attachments are files under the /uploads folder.
      // This is usually the trickiest step because you need to translate file paths.
      // If you need to export blobs from the database, see the vBulletin porter.

      if ($exportConversations) {
      // Conversations.
        $Conversations_Map = array(
            'unique_id' => 'CommentID',
            'RootID' => 'DiscussionID', // TODO!!!
            'user_id' => 'InsertUserID',
            'edit_user' => 'UpdateUserID',
            'body' => array('Column' => 'Body'),
            //'Format' => 'Format',
            'DateInserted' => 'DateInserted',
            'DateUpdated' => 'DateUpdated',
        );
        $Ex->ExportTable('Conversations', "
          SELECT m.*,
              FROM_UNIXTIME(m.post_date/1000, '%Y-%m-%d %H:%i:%s') as DateInserted,
              FROM_UNIXTIME(m.edit_date/1000, '%Y-%m-%d %H:%i:%s') as DateUpdated,
              r.unique_id as RootID
          FROM message2 m
          JOIN message2 r ON m.root_id = r.id AND m.node_id = r.node_id
          WHERE m.id != m.root_id AND m.deleted = 0 and m.node_id NOT IN (72, 73)", $Conversations_Map);
      }


      if ($exportSSO) {
      // Conversations.
        $SSO_Map = array(
            'sso_id' => 'ForeignUserKey',
            'ProviderKey' => 'ProviderKey',
            'id' => 'UserID',
        );
        $Ex->ExportTable('UserAuthentication', "
          SELECT sso_id,
              id,
              122254137 as ProviderKey
          FROM users", $SSO_Map);
      }


      $Ex->EndExport();
   }

}
// Closing PHP tag required.
?>