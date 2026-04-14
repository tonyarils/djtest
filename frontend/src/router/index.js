import { createRouter, createWebHistory } from 'vue-router'
import HomeView from '../views/home/HomeView.vue'
import OrgListView from '../views/org/OrgListView.vue'
import UserListView from '../views/user/UserListView.vue'
import TaskListView from '../views/task/TaskListView.vue'

const routes = [
  { path: '/', component: HomeView },
  { path: '/orgs', component: OrgListView },
  { path: '/users', component: UserListView },
  { path: '/tasks', component: TaskListView },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

export default router
