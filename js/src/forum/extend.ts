import Extend from 'flarum/common/extenders';
import commonExtend from '../common/extend';
import BadgesPage from './components/BadgesPage';
import UserBadgesPage from './components/UserBadgesPage';
import UserPageResolver from 'flarum/forum/resolvers/UserPageResolver';

export default [
  ...commonExtend,
  new Extend.Routes().add('badges', '/badges', BadgesPage).add('user.badges', '/u/:username/badges', UserBadgesPage, UserPageResolver),
];
