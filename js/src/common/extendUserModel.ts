import User from 'flarum/common/models/User';
import Model from 'flarum/common/Model';

export default function extendUserModel(): void {
  Object.assign(User.prototype, {
    classifiedsListingsCount: Model.attribute<number>('classifiedsListingsCount'),
  });
}
