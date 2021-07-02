<?php
namespace jcarloscid;

/**
 * @package       Moodle WS Helper
 * @author        Carlos Cid <carlos@fishandbits.es>
 * @copyright     Copyleft 2021 http://fishandbits.es
 * @license       GNU/GPL 2 or later
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307,USA.
 *
 * The "GNU General Public License" (GPL) is available at
 * http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 *
 */

/**
 * Moddle's web service helper class.
 */
class MoodleWSHelper {

  /**
   * Web service handler
   * @var object
   */
  private $moodle_rest;

  /**
   * Password used when creating users in Moodle
   * @var string
   */
  private $password = 'l!dd!hPQ0Eij';

 /**
  * Constructor. Creates the WS handler using the configuration parameters.
  */
  public function __construct($ws_end_point, $ws_token) {
    $this->moodle_rest = new \MoodleRest();
    $this->moodle_rest->setReturnFormat(\MoodleRest::RETURN_ARRAY);
    $this->moodle_rest->setServerAddress($ws_end_point);
    $this->moodle_rest->setToken($ws_token);
  }

  /**
   * Set the password used to create new moodle users.
   *
   * @param string $password New password
   */
  public function setPassword($password) {
    $this->password = $password;
  }

  /**
   * Tests the conection using the web service by means of retirveing the basic
   * site info. If the site name is retirved, the connection works.
   *
   * Required service function: core_webservice_get_site_info
   *
   * @return string Site name (works) or empty (doesn't work)
   */
  public function testWebService() {
    $site_name = null;
    try {
      $response = $this->moodle_rest->request('core_webservice_get_site_info', array(), \MoodleRest::METHOD_GET);
      if (!empty($response) and !empty($response['sitename'])) {
        $site_name = $response['sitename'];
      } else {
        error_log('[core_webservice_get_site_info] Response is empty, an exception or incomplete: ' . json_encode($response));
      }
    } catch (\Exception $e) {
      error_log('[core_webservice_get_site_info] Unexpected error: ' . $e->getMessage());
    } finally {
      return $site_name;
    }
  }

  /**
   * Retrieve the name from a single course from Moodle.
   *
   * Required service function: core_course_get_courses
   *
   * @param  int    $course_id Course ID
   * @return string            Name of the course. Blank if not found or on error.
   */
  public function getCourseName($course_id) {
    $course_name = '';
    $options = array( "options" => array( "ids" => array( $course_id ) ) );
    try {
      $response = $this->moodle_rest->request('core_course_get_courses', $options, \MoodleRest::METHOD_GET);
      if (is_array($response)) {
        $response = $response[0];
      }
      if (!empty($response) and !empty($response['fullname'])) {
        $course_name = $response['fullname'];
      } else {
        error_log('[core_course_get_courses] Response is empty, an exception or incomplete: ' . json_encode($response));
      }
    } catch (\Exception $e) {
      error_log('[core_course_get_courses] Unexpected error: ' . $e->getMessage());
    } finally {
      return $course_name;
    }
  }

  /**
   * Retrieve the name from a list of courses from Moodle.
   *
   * Required service function: core_course_get_courses
   *
   * @param  array $course_ids List of course ids (id1, id2, ...)
   * @return array             Dictionary of <id> => <name>
   */
  public function getCoursesNames($course_ids) {
    $courses = array();
    $options = array( "options" => array( "ids" => $course_ids ) );
    try {
      $response = $this->moodle_rest->request('core_course_get_courses', $options, \MoodleRest::METHOD_GET);
      if (!empty($response) and is_array($response) and !array_key_exists('exception', $response)) {
        foreach($response as $course) {
          $courses[$course['id']] = $course['fullname'];
        }
      } else {
        error_log('[core_course_get_courses] Response is empty, an exception or incomplete: ' . json_encode($response));
      }
    } catch (\Exception $e) {
      error_log('[core_course_get_courses] Unexpected error: ' . $e->getMessage());
    } finally {
      return $courses;
    }
  }

  /**
   * Get the list of roles defined by Moodle instance.
   *
   * Required service function: local_wsgetroles_get_roles
   * Required plugin: https://moodle.org/plugins/local_wsgetroles
   *
   * @return array Dictionary of <id> => <role-name>
   */
  public function getRoles() {
    $roles = array();
    try {
      $response = $this->moodle_rest->request('local_wsgetroles_get_roles', array(), \MoodleRest::METHOD_GET);
      if (!empty($response) and is_array($response) and !array_key_exists('exception', $response)) {
        foreach($response as $role) {
          $roles[$role['id']] = empty($role['name']) ? $role['shortname'] : $role['name'];
        }
      } else {
        error_log('[local_wsgetroles_get_roles] Response is empty, an exception or incomplete: ' . json_encode($response));
      }
    } catch (\Exception $e) {
      error_log('[local_wsgetroles_get_roles] Unexpected error: ' . $e->getMessage());
    } finally {
      return $roles;
    }
  }

  /**
   * Tries to retrieve the user ID of an exixting user with the same email.
   * If such a user does not exists, it tries to create a new one.
   *
   * @param  string $email     User email
   * @param  string $firstname User first name
   * @param  string $lastname  User last name
   * @return array             Operation result:
   *                             moodle_user_id: ID of the user
   *                             is_new: true if a new user was creted
   *                             error: false if completed correctly
   */
  public function getOrCreateUser($email, $firstname, $lastname) {
    $result = array();

    // Try to find a user with the same email
    $result['moodle_user_id'] = $this->getUser($email);

    // Tets if a user already exists
    if ($result['moodle_user_id'] === 0) {
      $result['is_new'] = true;

      // Create a new user
      $result['moodle_user_id'] = $this->createUser($email, $firstname, $lastname);
    } else {
      $result['is_new'] = false;
    }

    // A real User ID canot be 0
    $result['error'] = ($result['moodle_user_id'] === 0);

    return $result;
  }

  /**
   * Searches Moodle user database for a user with the same email.
   *
   * @param  string $email User email
   * @return int           ID of the existing user. 0 if not found
   */
  public function getUser($email) {
    $moodle_user_id = 0;
    $options = array( "field" => "email", "values" => array( $email ) );
    try {
      $response = $this->moodle_rest->request('core_user_get_users_by_field', $options, \MoodleRest::METHOD_POST);
      if (empty($response)) {
        $moodle_user_id = 0;                    // Not found
      } elseif (is_array($response) and !array_key_exists('exception', $response)) {
        $moodle_user_id = $response[0]['id'];   // Found
      } else {
        error_log('[core_user_get_users_by_field] Response is an exception or incomplete: ' . json_encode($response));
      }
    } catch (\Exception $e) {
      error_log('[core_user_get_users_by_field] Unexpected error: ' . $e->getMessage());
    } finally {
      return $moodle_user_id;
    }
  }

  /**
   * Creates a new Moodle user.
   *
   * @param  string $email     User email
   * @param  string $firstname User first name
   * @param  string $lastname  User last name
   * @return int               ID of the new user. 0 if user cannot be created.
   */
  public function createUser($email, $firstname, $lastname) {
    $moodle_user_id = 0;

    // Mandatory parameters for a new user
    $options['users'][0] = array( 'username'  => $email,
                                  'firstname' => $firstname,
                                  'lastname'  => $lastname,
                                  'email'     => $email);

    // Set password mode
    $default_passwd = $this->password;
    if (empty($default_passwd)) {
      $options['users'][0]['createpassword'] = true;        // Send a temporary password to the user
    } else {
      $options['users'][0]['password'] = $default_passwd;   // Use a password
    }

    try {
      // Create user WS call
      $response = $this->moodle_rest->request('core_user_create_users', $options, \MoodleRest::METHOD_POST);
      if (!empty($response) and is_array($response) and !array_key_exists('exception', $response)) {
        $moodle_user_id = $response[0]['id'];               // ID of the new user
      } else {
        error_log('[core_user_create_users] Response is empty, an exception or incomplete: ' . json_encode($response));
      }
    } catch (\Exception $e) {
      error_log('[core_user_create_users] Unexpected error: ' . $e->getMessage());
    } finally {
      return $moodle_user_id;
    }
  }

  /**
   * Enrol a moodle user in a course setting a given role. If duration is not
   * set, the enrolment will never exprire.
   *
   * @param  int $moodle_user_id     ID of the Moodel user
   * @param  int $course_id          ID of the Moodle course
   * @param  int $role_id            ID of the Moodle student role
   * @param  int $enrolment_duration Duration of the enrolment in days (0/false/null ==> never expires)
   * @return array                   Operation result:
   *                                    error: false if operation was successfull
   *                                    msg: error explanation
   */
  public function enrol($moodle_user_id, $course_id, $role_id, $enrolment_duration) {
    $result['msg'] = '';
    $result['error'] = true;

    //  Mandatory parameters for the nerolment
    $options['enrolments'][0] = array ('roleid' => $role_id,
                                       'userid' => $moodle_user_id,
                                       'courseid' => $course_id,
                                       'timestart' => time());

    // Assign enrolment end if enrolment_duration is set
    if (!empty($enrolment_duration)) {
      $options['enrolments'][0]['timeend'] = time() + ((int)$enrolment_duration * 24 * 60 * 60);
    }

    try {
      // Enrol student WS call
      $response = $this->moodle_rest->request('enrol_manual_enrol_users', $options, \MoodleRest::METHOD_POST);
      if (empty($response)) {
        $result['error'] = false;
      } else {
        error_log('[enrol_manual_enrol_users] Response is an exception or incomplete: ' . json_encode($response));
        $result['msg'] = 'Exception.';
      }
    } catch (\Exception $e) {
      error_log('[enrol_manual_enrol_users] Unexpected error: ' . $e->getMessage());
      $result['msg'] = 'Unexpected error.';
    } finally {
      return $result;
    }
  }
}
