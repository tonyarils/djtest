export const emptyOrgForm = () => ({
  name: '',
  parent_id: null,
  level: 1,
  org_type: '党委',
})

export const emptyUserForm = () => ({
  name: '',
  employee_no: '',
  org_id: null,
  party_role: '',
  job_title: '',
  gender: '',
  education: '',
  remark: '',
})

export const emptyTaskForm = () => ({
  title: '',
  description: '',
  task_type: 'A',
  status: '待领用',
  org_id: null,
  assignee_id: null,
  warning_level: 0,
  deadline_at: '',
})
