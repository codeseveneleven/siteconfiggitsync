# TYPO3 Site config - git synchronisation

[![Latest Stable Version](https://poser.pugx.org/code711/siteconfiggitsync/v/stable.svg)](https://extensions.typo3.org/code711/siteconfiggitsync/)
[![TYPO3 12](https://img.shields.io/badge/TYPO3-12-orange.svg)](https://get.typo3.org/version/12)
[![TYPO3 11](https://img.shields.io/badge/TYPO3-11-orange.svg)](https://get.typo3.org/version/11)
[![Total Downloads](https://poser.pugx.org/code711/siteconfiggitsync/d/total.svg)](https://packagist.org/packages/code711/siteconfiggitsync)
[![Monthly Downloads](https://poser.pugx.org/code711/siteconfiggitsync/d/monthly)](https://packagist.org/packages/code711/siteconfiggitsync)
![PHPSTAN:Level 9](https://img.shields.io/badge/PHPStan-level%208-brightgreen.svg?style=flat])
![build:passing](https://img.shields.io/badge/build-passing-brightgreen.svg?style=flat])

Sometimes it is necessary for a TYPO3 project that the site-config yaml files are managed in git alongside the site-package. Those projects have the problem that site-admins can then not change settings in the site config without manually carrying those changes back over to the git repository in order for them not to be overriden with the next CI/CD run or publish.

This extension aims to alleviate this problem by pushing those changes back to your gitlab instance automatically. In doing so the extension will create a branch, check in the changes that have been made and create a merge-request which you then can manage in gitlab without manually merging the yaml files. And you will be notified about the changes because of the merge-request.

There is no git binary on the server needed, everything is done through the GitLab API. Right now only GitLab is supported, but it can be selfhosted or on gitlab.com.

## Changelog

#### 1.0.3
- fixed issue [#1](https://github.com/codeseveneleven/siteconfiggitsync/issues/1)
- fixed issue reading the user-list in the extension configuration interface when a project is not part of a group
- added LoggerAwareInterface to AfterConfigurationWriteListener 

#### 1.0.1 
Fix README.md

#### 1.0.0 
Support TYPO3 12

#### 0.10.0 
Externalised the XCLASS of the SiteConfiguration Class to [EXT:siteconfigurationevents](https://extensions.typo3.org/extension/siteconfigurationevents). Composer installations should pull this extension automatically as a dependency. Refactored the git related actions into event listeners.
#### 0.9.x Initial release

## Setup / Installation

after installing this extension with <pre>composer req code711/siteconfiggitsync</pre> you will need to create an API Token in your GitLab Project Page with at least 'Developer' permissions.

This is done in your GitLab in your Project under 'Settings->Access Token'. Developer Permissions are needed because only from this level on Branches can be created and Merge-requests can be issued.

![Gitlab Backend](https://github.com/codeseveneleven/siteconfiggitsync/raw/main/Documentation/gitlab.png)

In the field 'Token name' enter something meaningfull for this task, as it will be written next to commits or merge-requests. For example 'External Site change from TYPO3'.

Set the 'Expiration Date' to your liking.

In 'Select a role' choose 'Developer'

And for the scope select 'api'. The other scopes are not enough for the tasks we want to do.

after pressing the 'Create project access token' button you will be given a series of alphanumeric character. Copy this string of character. This is your token.

![New Token](https://github.com/codeseveneleven/siteconfiggitsync/raw/main/Documentation/newtoken.png)

In your TYPO3 backend navigate to Settings->Extension Configuration and open the accordion for the siteconfiggitsync extension:

![Extension Config](https://github.com/codeseveneleven/siteconfiggitsync/raw/main/Documentation/extensionconfig.png)

In the field gitlab url add the complete URL to your projects repository, without any .git. Just as it is in your browser. for example <pre>https://gitlab.com/mycompany/customerproject </pre>

In the field Gitlab API Auth Token enter the token you just generated.

Now press the "Save 'siteconfiggitsync' Configuration" button, and close the window. Re-open the window and navigate again to the siteconfiggitsync accordion and open it. The fields 'main_branch' and 'Who to assign a merge request' should be filled with respectively the available branches in that project and members who are allowed to merge merge-requests.

Choose the branch which represents the production branch, i.e. from which the branch to commit the changes should be taken.

Optionaly choose a member who gets the merge-requests assigend to. If no member is chosen the merge-requests will not be assigend to anybody, and it depends on your setup if you are notified when one is created.

Now press the "Save 'siteconfiggitsync' Configuration" button again, and you should be ready to accept merge-requests when the site-config is changed through the backend interface.

## Notes and limitations

- renaming is not done with a git-move but with checking in a new file and removing the old one. The move operation did not work for me when developing this extension. This might be revisited later
- this extension is marked beta because not all scenarios have been tested. This is a 'works' for me right now
- The gitlab server must be reachable via http or https from the production server. The gitlab api implementation is using guzzlehttp, so a proxy configuration should be possible, but I have not looked into this yet. Help in this regard is welcome
- it should be possible to add bitbucket and github support. This might be added at a later stage

