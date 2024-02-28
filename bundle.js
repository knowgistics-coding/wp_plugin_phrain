import moment from "moment";
import simpleGit from "simple-git";
import { exec } from "child_process";
import fs from "fs";

const git = simpleGit();
const status = await git.status();
if (status.not_added.length > 0) {
  await git.add("./*");
  await git.commit(
    `updated ${status.not_added.join(", ")} files at ${moment().format(
      "YYYY-MM-DD HH:mm:ss"
    )}`
  );
}

await exec('npm version patch');

const packageJson = require("./package.json");
const pluginsJson = require("./plugins.json");

pluginsJson.version = packageJson.version;
pluginsJson.last_updated = moment().format("YYYY-MM-DD HH:mm:ss");
pluginsJson.download_url = `https://github.com/knowgistics-coding/wp_plugin_phrain/releases/download/${pluginsJson.version}/phrain.${pluginsJson.version}.zip`;

fs.writeFileSync("./plugins.json", JSON.stringify(pluginsJson, null, 2));

const main = fs.readFileSync("./phrain.php", "utf8");
const newMain = main.replace(
  /Version:\s*[\d.]+/,
  `Version: ${pluginsJson.version}`
);
fs.writeFileSync("./phrain.php", newMain);

await git.add("./*");
await git.commit(
  `update to version ${pluginsJson.version} at ${moment().format(
    "YYYY-MM-DD HH:mm:ss"
  )}`
);
await git.push("origin", "main");

await exec(`dir-archiver --src . --dest ../phrain.${pluginsJson.version}.zip --exclude .DS_Store .stylelintrc.json .eslintrc .git .gitattributes .github .gitignore README.md composer.json composer.lock node_modules vendor package-lock.json package.json .travis.yml phpcs.xml.dist sass style.css.map yarn.lock src bundle.js`);