import axios from 'axios'
export const api = axios.create({baseURL:import.meta.env.VITE_API_URL ?? 'http://localhost:8000',withCredentials:true,withXSRFToken:true,headers:{Accept:'application/json'}})
export type User = {id:number;name:string;email:string;role:string;organisation_id:number|null}
export async function currentUser(){return (await api.get<{data:User}>('/api/v1/auth/me')).data.data}
export async function login(email:string,password:string){await api.get('/sanctum/csrf-cookie');return (await api.post('/api/v1/auth/login',{email,password})).data.data.user as User}
