<?php
/**
 * @package Newscoop\NewsletterPluginBundle
 * @author Rafał Muszyński <rafal.muszynski@sourcefabric.org>
 * @copyright 2014 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\NewsletterPluginBundle\Services;

use Doctrine\ORM\EntityManager;
use Newscoop\NewsletterPluginBundle\TemplateList\ListCriteria;
use Newscoop\NewsletterPluginBundle\Entity\NewsletterList;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Newscoop\NewsletterPluginBundle\Entity\SubscribedUser;
use Newscoop\Entity\User;

/**
 * Newsletter service
 */
class NewsletterListsService
{
    /** @var Doctrine\ORM\EntityManager */
    protected $em;

    /** @var Newscoop\NewscoopBundle\Services\SystemPreferencesService */
    protected $preferencesService;

    /** @var Symfony\Component\HttpFoundation\Request */
    protected $request;

    /** @var Newscoop\Entity\User */
    protected $user;

    /** @var Symfony\Component\Translation\Translator */
    protected $translator;

    /**
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->em = $container->get('em');
        $this->preferencesService = $container->get('system_preferences_service');
        $this->request = $container->get('request');
        $this->user = $container->get('user');
        $this->translator = $container->get('translator');
    }

    /**
     * Subscribe not registered user to given list id
     *
     * @param string $id   Newsletter list id
     * @param string $type Newsletter type Html or text
     *
     * @return void
     */
    public function subscribePublic($id, $type)
    {
        try {
            $this->initMailchimp()->lists->subscribe($value,
                array(
                    'email' => $request->request->get('newsletter-lists-public-email')
                ),
                array(
                    'FNAME' => $request->request->get('newsletter-lists-public-firstname'),
                    'LNAME' => $request->request->get('newsletter-lists-public-lastname')
                ),
                $type
            );
        } catch (\Mailchimp_List_AlreadySubscribed $e) {
            return array(
                'message' => substr($e->getMessage(), 0, -35),
                'status' => false,
            );
        }

        return array(
            'message' => $this->translator->trans('plugin.newsletter.msg.successfully'),
            'status' => true,
        );
    }

    /**
     * Subscribe user to given list id
     *
     * @param string $id     Newsletter list id
     * @param string $type   Newsletter type Html or text
     * @param array  $groups Groups
     *
     * @return void
     */
    public function subscribeUser($id, $type, array $groups = array())
    {
        try {

            $mergeVars = array(
                'FNAME' => $this->user->getCurrentUser()->getFirstName(),
                'LNAME' => $this->user->getCurrentUser()->getLastName(),
            );

            if (!empty($groups)) {
                $groupings = array();
                $groupings[] = array(
                    'id' => $groups['id'],
                    'groups' => !empty($groups[0]) ? $groups[0] : array(''),
                );

                $mergeVars['GROUPINGS'] = array($groupings[0]);
            }

            $this->initMailchimp()->lists->subscribe($id,
                array(
                    'email' => $this->user->getCurrentUser()->getEmail()
                ),
                $mergeVars,
                $type, false, true, true, true
            );
        } catch (\Mailchimp_List_AlreadySubscribed $e) {
            $messageArray = explode('.', $e->getMessage());
            unset($messageArray[count($messageArray)-2]);

            throw new \Exception(implode('.', $messageArray));
        }
    }

    /**
     * Test if user email is subscribed to list
     *
     * @param string $email  User email
     * @param string $listId List id
     *
     * @return bool
     */
    public function isSubscribed($email, $listId)
    {
        try {
            $lists = $this->getLists(array('email' => $email));
            $result = array();
            foreach ($lists as $list) {
                if ($listId == $list['id']) {
                    $result[] = true;
                } else {
                    $result[] = false;
                }
            }

            return in_array(true, $result);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Unsubscribe email from list
     *
     * @param string $email  User email
     * @param string $listId List id
     *
     * @return void|Exception
     */
    public function unsubscribe($email, $listId)
    {
        try {
            $this->initMailchimp()->lists->unsubscribe($listId, array('email' => $email));
        } catch (\Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Get groups for given list id
     *
     * @param string $listId
     *
     * @return array
     */
    public function getListGroups($listId)
    {
        return $this->initMailchimp()->lists->interestGroupings($listId);
    }

    /**
     * Get groups for given user and list
     *
     * @param Newscoop\Entity\User $user
     * @param string               $listId
     *
     * @return array
     */
    public function getUserGroups($listId, $groupName)
    {
        $user = $this->user->getCurrentUser();
        if ($user) {
            $info = $this->initMailchimp()->lists->memberInfo($listId, array(array('email'=>$user->getEmail())));
            if (!$info['success_count']) {
                return array();
            }

            $groups = array();
            foreach ($info['data'] as $userinfo) {
                foreach ($userinfo['merges']['GROUPINGS'] as $grouping) {
                    $groups[$grouping['id']] = $grouping['groups'];
                }
            }

            foreach ($groups as $key => $value) {
                foreach ($value as $k => $v) {
                    if ($v['name'] == $groupName) {
                        return $v['interested'];
                    }
                }
            }
        }
    }

    /**
     * Get lists email is subscribed to
     *
     * @param string $email User email
     *
     * @return array
     */
    public function getLists($email)
    {
        $lists = $this->initMailchimp()->helper->listsForEmail($email);

        return $lists ?: array();
    }

    /**
     * Find by criteria
     *
     * @param ListCriteria         $criteria
     *
     * @return Newscoop\ListResult
     */
    public function findByCriteria(ListCriteria $criteria)
    {
        return $this->getRepository()->getListByCriteria($criteria);
    }

    /**
     * Initialize MailChimp library
     *
     * @return Mailchimp
     */
    public function initMailchimp()
    {
        return new \Mailchimp($this->preferencesService->mailchimp_apikey);
    }

    /**
     * Get mailchimp lists
     *
     * @param array $listId
     * @return array
     */
    public function getMailchimpLists($listId = array())
    {
        return $this->initMailchimp($this->preferencesService->mailchimp_apikey)->lists->getList($listId);
    }

    /**
     * Count by given criteria
     *
     * @param array $criteria
     * @return int
     */
    public function countBy(array $criteria = array())
    {
        return $this->getRepository()->findByCount($criteria);
    }

    /**
     * Get repository
     *
     * @return NewsletterList
     */
    protected function getRepository()
    {
        return $this->em->getRepository('Newscoop\NewsletterPluginBundle\Entity\NewsletterList');
    }
}