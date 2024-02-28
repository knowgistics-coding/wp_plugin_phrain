// exec command git add .
// exec command git commit -m "bundle.js"
// exec command git push origin master
// exec command git push heroku master

import moment from "moment";
import simpleGit from "simple-git";

const git = simpleGit();
const status = await git.status();
if (status.not_added.length > 0) {
  await git.add("./*");
  await git.commit(`updated ${status.not_added.length} files at ${moment().format("YYYY-MM-DD HH:mm:ss")}`);
}