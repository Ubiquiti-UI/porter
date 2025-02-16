<?php
/**
 * WebWiz exporter tool
 *
 * @copyright Vanilla Forums Inc. 2010
 * @license Proprietary
 * @package VanillaPorter
 */

$Supported['webwiz'] = array('name'=> 'Web Wiz Forums', 'prefix'=>'tbl');
$Supported['webwiz']['features'] = array(
   'Comments'        => 1,
   'Discussions'     => 1,
   'Users'           => 1,
   'Categories'      => 1,
   'Roles'           => 1,
   'Avatars'         => 1,
   'PrivateMessages' => 1,
   'Signatures'      => 1,
   'Passwords'       => 1,
);

class WebWiz extends ExportController {
   /**
    *
    * @param ExportModel $Ex 
    */
   public function ForumExport($Ex) {
      // Get the characterset for the comments.
      $CharacterSet = $Ex->GetCharacterSet('tblTopic');
      if ($CharacterSet)
         $Ex->CharacterSet = $CharacterSet;
      
      $Ex->BeginExport('', 'Web Wiz Forums');
      $Ex->SourcePrefix = 'tbl';
      
//      // Permissions.
//      $Permission_Map = array(
//          'group_id' => 'RoleID',
//          'can_access_cp' => 'Garden.Settings.View',
//          'can_access_edit' => 'Vanilla.Discussions.Edit',
//          'can_edit_all_comments' => 'Vanilla.Comments.Edit',
//          'can_access_admin' => 'Garden.Settings.Manage',
//          'can_admin_members' => 'Garden.Users.Edit',
//          'can_moderate_comments' => 'Garden.Moderation.Manage',
//          'can_view_profiles' => 'Garden.Profiles.View',
//          'can_post_comments' => 'Vanilla.Comments.Add',
//          'can_view_online_system' => 'Vanilla.Discussions.View',
//          'can_sign_in' => 'Garden.SignIn.Allow',
//          'can_view_profiles3' => 'Garden.Activity.View',
//          'can_post_comments2' => 'Vanilla.Discussions.Add'
//      );
//      $Permission_Map = $Ex->FixPermissionColumns($Permission_Map);
//      foreach ($Permission_Map as $Column => &$Info) {
//         if (is_array($Info) && isset($Info['Column']))
//            $Info['Filter'] = array($this, 'Bool');
//      }
//      
//      $Ex->ExportTable('Permission', "
//         select 
//            g.can_view_profiles as can_view_profiles2,
//            g.can_view_profiles as can_view_profiles3,
//            g.can_post_comments as can_post_comments2,
//            g.can_post_comments as can_sign_in,
//            case when can_access_admin = 'y' then 'all' when can_view_online_system = 'y' then 'view' end as _Permissions,
//            g.*
//         from forum_member_groups g
//      ", $Permission_Map);
      
      
      // User.
      $User_Map = array(
          'Author_ID' => 'UserID',
          'Username' => array('Column' => 'Name', 'Filter' => 'HTMLDecoder'),
          'Real_name' => array('Column' => 'FullName', 'Type' => 'varchar(50)', 'Filter' => 'HTMLDecoder'),
          'Password2' => 'Password',
          'Gender2' => 'Gender',
          'Author_email' => 'Email',
          'Photo2' => array('Column' => 'Photo', 'Filter' => 'HTMLDecoder'),
          'Login_IP' => 'LastIPAddress',
          'Banned' => 'Banned',
          'Join_date' => array('Column' => 'DateInserted'),
          'Last_visit' => array('Column' => 'DateLastActive'),
          'Location' => array('Column' => 'Location', 'Filter' => 'HTMLDecoder'),
          'DOB' => 'DateOfBirth',
          'Show_email' => 'ShowEmail'
          );
      $Ex->ExportTable('User', "
         select
            concat(Salt, '$', Password) as Password2,
            case u.Gender when 'Male' then 'm' when 'Female' then 'f' else 'u' end as Gender2,
         case when Avatar like 'http%' then Avatar when Avatar > '' then concat('webwiz/', Avatar) else null end as Photo2,
            'webwiz' as HashMethod,
            u.*
         from tblAuthor u
         ", $User_Map);
      
      
      // Role.
      $Role_Map = array(
          'Group_ID' => 'RoleID',
          'Name' => 'Name'
      );
      $Ex->ExportTable('Role', "
         select *
         from tblGroup", $Role_Map);
      
      // User Role.
      $UserRole_Map = array(
          'Author_ID' => 'UserID',
          'Group_ID' => 'RoleID'
      );
      $Ex->ExportTable('UserRole', "
         select u.*
         from tblAuthor u", $UserRole_Map);
      
      // UserMeta
      $Ex->ExportTable('UserMeta', "
         select
            Author_ID as UserID,
            'Plugin.Signatures.Sig' as `Name`,
            Signature as `Value`
         from tblAuthor
         where Signature <> ''");
      
      // Category.
      $Category_Map = array(
          'Forum_ID' => 'CategoryID',
          'Forum_name' => 'Name',
          'Forum_description' => 'Description',
          'Parent_ID' => 'ParentCategoryID',
          'Forum_order' => 'Sort'
      );
      $Ex->ExportTable('Category', "
         select
            f.Forum_ID,
            f.Cat_ID * 1000 as Parent_ID,
            f.Forum_order,
            f.Forum_name,
            f.Forum_description
         from tblForum f

         union all

         select
            c.Cat_ID * 1000,
            null,
            c.Cat_order,
            c.Cat_name,
            null
         from tblCategory c
         ", $Category_Map);
      
      // Discussion.
      $Discussion_Map = array(
          'Topic_ID' => 'DiscussionID',
          'Forum_ID' => 'CategoryID',
          'Author_ID' => 'InsertUserID',
          'Subject' => array('Column' => 'Name', 'Filter' => 'HTMLDecoder'),
          'IP_addr' => 'InsertIPAddress',
          'Message' => array('Column' => 'Body'),
          'Format' => 'Format',
          'Message_date' => array('Column' => 'DateInserted'),
          'No_of_views' => 'CountViews',
          'Locked' => 'Closed',
          
          );
      $Ex->ExportTable('Discussion', "
         select 
            th.Author_ID,
            th.Message,
            th.Message_date,
            th.IP_addr,
            'Html' as Format,
            t.*
         from tblTopic t
         join tblThread th
            on t.Start_Thread_ID = th.Thread_ID", $Discussion_Map);
      
      // Comment.
      $Comment_Map = array(
          'Thread_ID' => 'CommentID',
          'Topic_ID' => 'DiscussionID',
          'Author_ID' => 'InsertUserID',
          'IP_addr' => 'InsertIPAddress',
          'Message' => array('Column' => 'Body'),
          'Format' => 'Format',
          'Message_date' => array('Column' => 'DateInserted')
      );
      $Ex->ExportTable('Comment', "
      select
         th.*,
         'Html' as Format
      from tblThread th
      join tblTopic t
         on t.Topic_ID = th.Topic_ID
      where th.Thread_ID <> t.Start_Thread_ID", $Comment_Map);
      
      $this->ExportConversations();
      
      $Ex->EndExport();
   }
   
   public function ExportConversations() {
      $Ex = $this->Ex;
      
      $this->_ExportConversationTemps();
      
      // Conversation.
      $Conversation_Map = array(
          'PM_ID' => 'ConversationID',
          'Title' => array('Column' => 'Subject', 'Type' => 'varchar(255)'),
          'Author_ID' => 'InsertUserID',
          'PM_Message_Date' => array('Column' => 'DateInserted')
      );
      $Ex->ExportTable('Conversation', "
         select
            pm.*,
            g.Title
         from tblPMMessage pm
         join z_pmgroup g
            on pm.PM_ID = g.Group_ID;", $Conversation_Map);
      
      // User Conversation.
      $UserConversation_Map = array(
          'Group_ID' => 'ConversationID',
          'User_ID' => 'UserID');
      $Ex->ExportTable('UserConversation', "
         select
            g.Group_ID,
            t.User_ID
         from z_pmto t
         join z_pmgroup g
            on g.Group_ID = t.PM_ID;", $UserConversation_Map);
      
      // Conversation Message.
      $Message_Map = array(
          'Group_ID' => 'ConversationID',
          'PM_ID' => 'MessageID',
          'PM_Message' => 'Body',
          'Format' => 'Format',
          'PM_Message_Date' => array('Column' => 'DateInserted'),
          'Author_ID' => 'InsertUserID'
          );
      $Ex->ExportTable('ConversationMessage', "
         select
            pm.*,
            pm2.Group_ID,
            'Html' as Format
          from tblPMMessage pm
          join z_pmtext pm2
            on pm.PM_ID = pm2.PM_ID;", $Message_Map);
   }
   
   protected function _ExportConversationTemps() {
      $Sql = "
         drop table if exists z_pmto;

         create table z_pmto (
            PM_ID int unsigned,
            User_ID int,
            primary key(PM_ID, User_ID)
            );

         insert ignore z_pmto (
            PM_ID,
            User_ID
         )
         select
            PM_ID,
            Author_ID
         from tblPMMessage;

         insert ignore z_pmto (
            PM_ID,
            User_ID
         )
         select
            PM_ID,
            From_ID
         from tblPMMessage;

         drop table if exists z_pmto2;
         create table z_pmto2 (
            PM_ID int unsigned,
             UserIDs varchar(250),
             primary key (PM_ID)
         );

         replace z_pmto2 (
            PM_ID,
            UserIDs
         )
         select
            PM_ID,
            group_concat(User_ID order by User_ID)
         from z_pmto
         group by PM_ID;

         drop table if exists z_pmtext;
         create table z_pmtext (
            PM_ID int unsigned,
            Title varchar(250),
             Title2 varchar(250),
             UserIDs varchar(250),
             Group_ID int unsigned
         );

         insert z_pmtext (
            PM_ID,
            Title,
            Title2
         )
         select
            PM_ID,
            PM_Tittle,
            case when PM_Tittle like 'Re:%' then trim(substring(PM_Tittle, 4)) else PM_Tittle end as Title2
         from tblPMMessage;

         create index z_idx_pmtext on z_pmtext (PM_ID);

         update z_pmtext pm
         join z_pmto2 t
            on pm.PM_ID = t.PM_ID
         set pm.UserIDs = t.UserIDs;

         drop table if exists z_pmgroup;

         create table z_pmgroup (
                 Group_ID int unsigned,
                 Title varchar(250),
                 UserIDs varchar(250)
               );

         insert z_pmgroup (
                 Group_ID,
                 Title,
                 UserIDs
               )
               select
                 min(pm.PM_ID),
                 pm.Title2,
                 t2.UserIDs
               from z_pmtext pm
               join z_pmto2 t2
                 on pm.PM_ID = t2.PM_ID
               group by pm.Title2, t2.UserIDs;

         create index z_idx_pmgroup on z_pmgroup (Title, UserIDs);
         create index z_idx_pmgroup2 on z_pmgroup (Group_ID);

         update z_pmtext pm
               join z_pmgroup g
                 on pm.Title2 = g.Title and pm.UserIDs = g.UserIDs
               set pm.Group_ID = g.Group_ID;";
      
      $this->Ex->QueryN($Sql);
   }
}
?>