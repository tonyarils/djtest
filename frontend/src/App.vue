<template>
  <el-container class="layout">
    <el-aside width="220px" class="sidebar">
      <div class="brand">智慧红芯</div>
      <el-menu :default-active="$route.path" router>
        <el-menu-item index="/">首页</el-menu-item>
        <el-menu-item index="/orgs">组织管理</el-menu-item>
        <el-menu-item index="/users">党员管理</el-menu-item>
        <el-menu-item index="/tasks">任务管理</el-menu-item>
      </el-menu>
    </el-aside>
    <el-container>
      <el-header class="header">党建协同平台</el-header>
      <el-main>
        <router-view />
      </el-main>
    </el-container>
  </el-container>
</template>

<style scoped>
.layout {
  min-height: 100vh;
}
.sidebar {
  background: #1f2d3d;
}
.brand {
  color: #fff;
  font-size: 20px;
  font-weight: 700;
  padding: 20px;
}
.header {
  display: flex;
  align-items: center;
  font-size: 18px;
  font-weight: 600;
  border-bottom: 1px solid #ebeef5;
}
</style>
